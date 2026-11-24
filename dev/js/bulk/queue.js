import { isDoneFragment } from '../common/rowStatus';

/**
 * Maximum number of poll attempts before giving up on a single file.
 * At 1 s per poll this caps the wait at ~5 minutes, matching ILOVE_IMG_WM_PROCESS_TIMEOUT.
 */
var MAX_POLLS = 300;

/**
 * Maximum times a status-3 (queued/blocked) file is re-queued within one pool run.
 * Prevents an infinite re-queue loop when the server gate is saturated for a prolonged period.
 * Once exhausted the file is counted as failed rather than hanging forever.
 */
var MAX_QUEUE_RETRIES = 5;

/**
 * Resolve after `ms` milliseconds.
 *
 * @param {number} ms
 * @returns {Promise<void>}
 */
function sleep( ms ) {
	return new Promise( function ( resolve ) {
		setTimeout( resolve, ms );
	} );
}

/**
 * POST a single-file watermark request to admin-ajax.php and return the
 * raw HTML fragment body.  Returns '' on any HTTP error (non-2xx) or if the
 * response body is empty (including HTTP 202 "still processing" replies).
 *
 * @param {string} action  WP AJAX action name.
 * @param {string} id      Attachment post ID (string or number).
 * @param {string} nonce   Action-scoped nonce value.
 * @returns {Promise<string>}
 */
async function postFragment( action, id, nonce ) {
	var body = new URLSearchParams( { action: action, id: id, imgnonce: nonce } );
	var res = await fetch( ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' } );
	if ( ! res.ok ) {
		return '';
	}
	return res.text();
}

/**
 * Detect whether an HTML fragment contains the in-flight poll div that the server
 * emits when the attachment is still being processed (status 1 or 3).
 *
 * @param {string} html
 * @returns {boolean}
 */
function hasPollDiv( html ) {
	return typeof html === 'string' && html.indexOf( 'iloveimg_watermarking' ) !== -1;
}

/**
 * Poll `pollAction` at 1-second intervals until the server returns a non-empty,
 * settled fragment, or until MAX_POLLS is reached.
 *
 * Server poll contract (after T01 fix):
 *   HTTP 202 → genuinely in-flight ("still processing" — do NOT read body, keep polling).
 *   HTTP non-OK → ambiguous, keep polling.
 *   HTTP 200, empty body → keep polling.
 *   HTTP 200, non-empty body → settled state: success marker, error fragment, or queued div.
 *
 * MAX_POLLS is a hard backstop only; in practice ILOVE_IMG_WM_PROCESS_TIMEOUT (300 s) on the
 * server will produce a terminal status-0 fragment long before MAX_POLLS is exhausted.
 *
 * @param {string} id         Attachment post ID.
 * @param {string} nonce      Action-scoped nonce value.
 * @param {string} pollAction WP AJAX action name for polling.
 * @returns {Promise<string>} The settled HTML fragment, or '' on timeout/give-up.
 */
export async function pollUntilSettled( id, nonce, pollAction ) {
	for ( var attempt = 0; attempt < MAX_POLLS; attempt++ ) {
		await sleep( 1000 );
		var res = await fetch( ajaxurl, {
			method: 'POST',
			body: new URLSearchParams( {
				action: pollAction,
				id: id,
				imgnonce: nonce,
			} ),
			credentials: 'same-origin',
		} );

		// HTTP 202 = server explicitly says "still in flight" — skip body read and keep polling.
		if ( res.status === 202 ) {
			continue;
		}

		var html = await res.text();

		// Non-OK response or empty body = ambiguous or still in-flight — keep polling.
		if ( ! res.ok || html.trim() === '' ) {
			continue;
		}

		// Non-empty body = terminal state (success, error, or queued/status-3 fragment).
		return html;
	}
	return ''; // MAX_POLLS backstop — ILOVE_IMG_WM_PROCESS_TIMEOUT should terminate first.
}

/**
 * Run a bounded concurrent worker pool over a list of attachment IDs.
 *
 * Pool semantics:
 *  - Up to K files are in flight simultaneously (K = localized `bulkConcurrency`, fallback 3).
 *    K is aligned to the server's concurrency gate so status-3 "orphaned queued" files are rare.
 *  - When a slot frees (file settles), the next queued id is immediately dispatched into it.
 *  - Trigger path: empty body, hasPollDiv fragment, or fetch throw → route to pollUntilSettled.
 *  - Poll path: 202 or empty → keep polling; isDoneFragment → terminal success;
 *    hasPollDiv → re-queue with bounded retries (MAX_QUEUE_RETRIES); other non-empty → error.
 *
 * @param {object}   options
 * @param {string[]} options.ids          Attachment IDs to process.
 * @param {string}   options.action       WP AJAX action for triggering watermark.
 * @param {string}   options.pollAction   WP AJAX action for polling status.
 * @param {string}   options.nonce        Action-scoped nonce (`iloveimgWatermark.nonce`).
 * @param {Function} options.onProgress   Called after each file settles: `({ done, total })`.
 * @param {Function} options.onFileDone   Called after each file settles: `(id, ok, html)`.
 * @param {Function} [options.onFileStart] Called before each trigger request: `(id)`.
 * @returns {Promise<{ succeeded: number, failed: number }>}
 */
export function runQueue( { ids, action, pollAction, nonce, onProgress, onFileDone, onFileStart } ) {
	// K: max concurrent in-flight slots. Align with server gate to minimise status-3 occurrences.
	var K = ( window.iloveimgWatermark && iloveimgWatermark.bulkConcurrency )
		? parseInt( iloveimgWatermark.bulkConcurrency, 10 ) : 3;
	if ( ! K || K < 1 ) {
		K = 3;
	}

	var total     = ids.length;
	var queue     = ids.slice(); // remaining ids (mutable; re-queued ids go to the back)
	var retries   = {};          // id -> re-queue count for status-3 handling
	var done      = 0;           // settled files (success + failure, not counting re-queues)
	var succeeded = 0;
	var failed    = 0;

	return new Promise( function ( resolve ) {
		var inFlight = 0;

		/**
		 * Fill all free pool slots from the queue.
		 * When slots and queue are both empty every id has settled — resolve the pool.
		 */
		function tryDispatch() {
			while ( inFlight < K && queue.length > 0 ) {
				var id = queue.shift();
				inFlight++;
				void processId( id ); // intentional fire-and-forget; completion tracked via inFlight counter
			}
			if ( inFlight === 0 && queue.length === 0 ) {
				resolve( { succeeded: succeeded, failed: failed } );
			}
		}

		/**
		 * Process a single attachment id through trigger → (optional poll) → outcome.
		 * Calls back into tryDispatch() once the slot is free.
		 *
		 * @param {string|number} id
		 */
		async function processId( id ) {
			if ( onFileStart && ! retries[ id ] ) {
				onFileStart( id );
			}

			var settledHtml = '';

			try {
				var html = await postFragment( action, id, nonce );
				// Empty body or hasPollDiv = trigger response signals "still in flight".
				// Route to the poll loop so the client always reaches a terminal state,
				// even when the proxy cut the trigger request at the 40 s timeout.
				if ( html.trim() === '' || hasPollDiv( html ) ) {
					settledHtml = await pollUntilSettled( id, nonce, pollAction );
				} else {
					settledHtml = html;
				}
			} catch ( e ) {
				// Network/transport throw: the server may have already started the job, so poll
				// through to the true outcome rather than concluding failure immediately.
				console.error( '[iloveimg wm queue] file', id, e );
				try {
					settledHtml = await pollUntilSettled( id, nonce, pollAction );
				} catch ( pollErr ) {
					console.error( '[iloveimg wm queue] poll after transport failure', id, pollErr );
					settledHtml = '';
				}
			}

			// --- Classify the settled result and update pool accounting ---

			if ( isDoneFragment( settledHtml ) ) {
				// Terminal success.
				succeeded++;
				done++;
				onFileDone( id, true, settledHtml );
				onProgress( { done: done, total: total } );
			} else if ( hasPollDiv( settledHtml ) ) {
				// Server returned a queued/in-flight div even after MAX_POLLS — the server
				// gate is saturated. Re-queue the id for a later retry rather than counting
				// it as done, so it gets another shot once a slot frees and the gate opens.
				var r = ( retries[ id ] || 0 );
				if ( r < MAX_QUEUE_RETRIES ) {
					retries[ id ] = r + 1;
					queue.push( id ); // back of the pool queue; total and done unchanged
				} else {
					// Bounded retries exhausted — count as failed rather than hanging forever.
					failed++;
					done++;
					onFileDone( id, false, settledHtml );
					onProgress( { done: done, total: total } );
				}
			} else {
				// Terminal error: empty string (MAX_POLLS backstop) or non-empty error fragment.
				failed++;
				done++;
				onFileDone( id, false, settledHtml );
				onProgress( { done: done, total: total } );
			}

			inFlight--;
			tryDispatch();
		}

		tryDispatch();
	} );
}
