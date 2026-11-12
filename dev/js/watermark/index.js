import { __ } from '@wordpress/i18n';
import { sprintf } from '@wordpress/i18n';
import { showAdminNotice } from '../components';

var batchInProgress = false;
// Module-level counters for bulk-batch status tracking.
// Reset to 0 at the start of each batch in watermarkAll().
var batchSuccesses = 0;
var batchErrors = 0;
// Per page-load set of attachment ids that have already received a success notice.
// Prevents stacking duplicate "Image watermarked successfully" notices when the
// same id resolves to a done fragment more than once (e.g. trigger + poller both fire).
const notifiedIds = new Set();

/**
 * Detect whether an HTML fragment represents a completed watermark result.
 *
 * Keys off the `.iloveimg_sizes_watermarked` anchor class that
 * Ilove_Img_Wm_Resources::render_watermark_details() always emits (Resources.php ~line 356).
 * This is the render→JS coupling point: if that class is ever renamed, detection
 * silently stops — update both sides together.
 * CONTRACT: `iloveimg_sizes_watermarked` is the shared completion class between this JS check
 * and the `class="thickbox iloveimg_sizes_watermarked"` anchor in render_watermark_details().
 * Never rename on one side only.
 *
 * @param {string} html
 * @returns {boolean}
 */
function isDoneFragment( html ) {
    return typeof html === 'string' && html.indexOf( 'iloveimg_sizes_watermarked' ) !== -1;
}

/**
 * Set the overview panel watermarked/total counts from authoritative server values
 * carried in the AJAX done-response HTML, then recompute the bar/percent text.
 *
 * The server embeds a hidden <span class="iloveimg-wm-counts"
 *   data-iloveimg-wm-count="N" data-iloveimg-total-count="M"> inside the done
 * fragment (emitted by Ilove_Img_Wm_Resources::render_overview_counts()).
 * We SET (never increment) the overview attributes so the call is idempotent:
 * calling it N times for the same completed image yields the correct value once.
 *
 * No-ops silently when the overview panel or the counts element is absent
 * (e.g. upload.php without the bulk-actions submenu, or legacy responses without
 * the hidden element).
 *
 * @param {string} html  The raw HTML response from an AJAX done-fragment.
 */
function applyOverviewCounts( html ) {
    var $wmCount = jQuery( '[data-iloveimg-wm-count]' );
    if ( ! $wmCount.length ) {
        return;
    }
    var $counts = jQuery( '<div>' ).html( html ).find( '.iloveimg-wm-counts' );
    if ( ! $counts.length ) {
        return;
    }
    var watermarked = parseInt( $counts.attr( 'data-iloveimg-wm-count' ), 10 );
    var total = parseInt( $counts.attr( 'data-iloveimg-total-count' ), 10 );
    if ( isNaN( watermarked ) || isNaN( total ) ) {
        return;
    }
    var $totalCount = jQuery( '[data-iloveimg-total-count]' );
    $wmCount.text( watermarked );
    $totalCount.text( total );
    var percent = total > 0 ? Math.round( ( watermarked / total ) * 100 ) : 0;
    jQuery( '.iloveimg_percent-total' ).width( percent + '%' );
    jQuery( '[data-iloveimg-wm-percent]' ).text( percent + '%' );
}

/**
 * Attach a setInterval poller to a newly-injected .iloveimg_watermarking element.
 *
 * Called after any container.html(data) swap that re-injects a polling element
 * (queued/status=3 or still-processing/status=1) so the row resolves without a
 * manual page reload (W5 — re-bind fix for status=3 hang).
 *
 * @param {HTMLElement} element  The .iloveimg_watermarking DOM node.
 */
function attachPollerToElement( element ) {
    var newTimesIntervals = {};
    var newIndex = 'swap_' + Date.now() + '_' + Math.floor( Math.random() * 10000 );
    newTimesIntervals[ 'ref_' + newIndex ] = setInterval( function () {
        statusWatermark( element, newIndex, newTimesIntervals );
    }, 1000 );
}

/**
 * Watermark image via AJAX
 * @param {Event} event
 */
export function watermarkImage( event ) {
    var element = jQuery( event.target );
    var container = element.closest( 'td' );
    /* translators: Shown in admin notice when watermarking fails. */
    var failedMsg = __( 'Watermarking failed. Please try again.', 'iloveimg-watermark' );

    element.attr( 'disabled', 'disabled' );
    // Spinner class coupling: the <img> in get_status_of_column() carries class="iloveimg-spinner"
    // so this selector matches it; using .next() scopes to siblings of the clicked button.
    element.next( '.iloveimg-spinner' ).show();

    var requestData = {
        action: 'ilove_img_wm_library',
        id: element.data( 'id' ) || element.attr( 'data-id' ),
        imgnonce: element.data( 'imgnonce' ) || element.attr( 'data-imgnonce' ),
    };

    jQuery.ajax( {
        url: ajaxurl,
        type: 'POST',
        data: requestData,
        success: function ( data ) {
            if ( typeof data === 'string' && data.trim() !== '' ) {
                // Non-empty body: update the row in place.
                container.html( data );
                // Re-bind poller if the swap re-injected a polling element (queued/status=3 fix).
                var $newPolling = container.find( '.iloveimg_watermarking' );
                if ( $newPolling.length ) {
                    attachPollerToElement( $newPolling[ 0 ] );
                } else if ( ! batchInProgress ) {
                    // Single-file: show a dismissible notice — no page reload.
                    if ( isDoneFragment( data ) ) {
                        applyOverviewCounts( data );
                        var id = element.data( 'id' ) || element.attr( 'data-id' );
                        if ( ! notifiedIds.has( id ) ) {
                            notifiedIds.add( id );
                            showAdminNotice( __( 'Image watermarked successfully.', 'iloveimg-watermark' ), 'success' );
                        }
                    } else {
                        showAdminNotice( __( 'Watermarking failed. Please try again.', 'iloveimg-watermark' ), 'error' );
                    }
                } else {
                    // Batch: track outcome for the barrier in watermarkAll().
                    if ( isDoneFragment( data ) ) {
                        applyOverviewCounts( data );
                        batchSuccesses++;
                    } else {
                        batchErrors++;
                    }
                }
            } else {
                // Empty body = JS-only failure (e.g. nonce mismatch); re-enable button for retry.
                element.removeAttr( 'disabled' );
                element.next( '.iloveimg-spinner' ).hide();
                if ( batchInProgress ) {
                    batchErrors++;
                } else {
                    showAdminNotice( failedMsg, 'error' );
                }
            }
        },
        error: function () {
            // Transport / HTTP error: re-enable button, show notice for single; count for batch.
            element.removeAttr( 'disabled' );
            element.next( '.iloveimg-spinner' ).hide();
            if ( batchInProgress ) {
                // Transport-ERROR increments batchErrors so the counted barrier can fire (W6).
                batchErrors++;
            } else {
                showAdminNotice( failedMsg, 'error' );
            }
        },
    } );
}

/**
 * Check watermark status (poller callback)
 * @param {HTMLElement} element
 * @param {number|string} index
 * @param {Object} timesIntervals
 */
export function statusWatermark( element, index, timesIntervals ) {
    var $element = jQuery( element );
    // Stop orphaned pollers: if this poller's row was already swapped out by a
    // container.html() update, the node is detached — its closest('td') is empty so updates
    // no-op, but a success/failure notice would still fire globally. Clear and bail.
    if ( ! document.body.contains( element ) ) {
        clearInterval( timesIntervals[ 'ref_' + index ] );
        return;
    }
    var container = $element.closest( 'td' );
    /* translators: Shown in admin notice when watermarking polling fails repeatedly. */
    var failedMsg = __( 'Watermarking failed. Please try again.', 'iloveimg-watermark' );

    // Maximum consecutive non-500 transport failures before we give up polling for this row.
    // Scoped per row via jQuery .data() so one row's network issues don't affect other rows.
    var POLL_FAIL_THRESHOLD = 3;

    jQuery.ajax( {
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'ilove_img_wm_library_is_watermarked',
            id: $element.data( 'id' ) || $element.attr( 'data-id' ),
            imgnonce: $element.data( 'imgnonce' ) || $element.attr( 'data-imgnonce' ),
        },
        success: function ( data ) {
            // A HTTP 500 from a fresh in-flight job goes to the error callback instead.
            // Guard: never blank the <td> on an empty or whitespace-only response.
            if ( typeof data === 'string' && data.trim() !== '' ) {
                clearInterval( timesIntervals[ 'ref_' + index ] );
                container.html( data );
                // Re-bind poller if the swap re-injected a polling element (queued/status=3 fix).
                var $newPolling = container.find( '.iloveimg_watermarking' );
                if ( $newPolling.length ) {
                    attachPollerToElement( $newPolling[ 0 ] );
                } else {
                    // Poll resolved to completion (done or error fragment).
                    if ( ! batchInProgress ) {
                        if ( isDoneFragment( data ) ) {
                            applyOverviewCounts( data );
                            var pollId = $element.data( 'id' ) || $element.attr( 'data-id' );
                            if ( ! notifiedIds.has( pollId ) ) {
                                notifiedIds.add( pollId );
                                showAdminNotice( __( 'Image watermarked successfully.', 'iloveimg-watermark' ), 'success' );
                            }
                        } else {
                            showAdminNotice( __( 'Watermarking failed. Please try again.', 'iloveimg-watermark' ), 'error' );
                        }
                    } else {
                        if ( isDoneFragment( data ) ) {
                            applyOverviewCounts( data );
                            batchSuccesses++;
                        } else {
                            batchErrors++;
                        }
                    }
                }
            }
            // If empty: leave the existing cell untouched and keep polling.
        },
        error: function ( jqXHR ) {
            // HTTP 500 means the job is still genuinely in-flight (server contract): keep polling.
            // Any other status is a real transport failure (network down, 502/504, DNS).
            if ( jqXHR.status === 500 ) {
                return;
            }

            // Count consecutive non-500 failures per row (stored on the element via jQuery .data()
            // so separate rows do not share a counter). After POLL_FAIL_THRESHOLD failures we stop
            // the interval and surface an error so the spinner never spins indefinitely.
            var failCount = ( $element.data( 'iloveimg-fail-count' ) || 0 ) + 1;
            $element.data( 'iloveimg-fail-count', failCount );

            if ( failCount >= POLL_FAIL_THRESHOLD ) {
                clearInterval( timesIntervals[ 'ref_' + index ] );
                $element.data( 'iloveimg-fail-count', 0 );

                if ( batchInProgress ) {
                    // Batch: increment counter so the barrier in watermarkAll() can fire.
                    batchErrors++;
                } else {
                    // Single: re-enable button so the user can retry manually.
                    container.find( 'button.iloveimg-watermark' ).removeAttr( 'disabled' );
                    // Hide spinner (class coupling mirrors the img class set in get_status_of_column()).
                    container.find( '.iloveimg-spinner' ).hide();
                    showAdminNotice( failedMsg, 'error' );
                }
            }
        },
    } );
}

/**
 * Handle watermark all button.
 *
 * Uses a counted barrier — (batchSuccesses + batchErrors) >= total — instead of a DOM-count
 * or reload barrier. Transport errors increment batchErrors so the barrier is never stuck.
 * A max-tick cap provides a backstop against missing callbacks.
 *
 * @param {Event} event
 */
export function watermarkAll( event ) {
    batchInProgress = true;
    // Reset per-batch counters so a fresh run starts clean.
    batchSuccesses = 0;
    batchErrors = 0;
    var totalImagesToWatermark = jQuery( 'button.iloveimg-watermark' ).length;

    // NaN guard: nothing to process.
    if ( totalImagesToWatermark === 0 ) {
        return;
    }

    jQuery( 'button#iloveimg_watermarkall' ).attr( 'disabled', 'disabled' ).addClass( 'is-processing' );

    // Trigger all per-row clicks first, then set up ONE shared interval.
    jQuery( 'button.iloveimg-watermark' ).each( function ( index, element ) {
        jQuery( element ).trigger( 'click' );
    } );

    // Dynamic cap: gives each image ~200 ticks of 300 ms (parity with compress plugin).
    var MAX_TICKS = totalImagesToWatermark * 200 + 100;
    var ticks = 0;

    // Single interval monitors overall completion via counted barrier.
    var timeReload = setInterval( function () {
        ticks++;
        var completed = batchSuccesses + batchErrors;
        var _percent = ( completed / totalImagesToWatermark ) * 100;
        jQuery( 'button#iloveimg_watermarkall .iloveimg-watermark-all__percent' ).width( _percent + '%' );

        if ( completed >= totalImagesToWatermark || ticks >= MAX_TICKS ) {
            clearInterval( timeReload );
            // Set bar to 100% and show finished state.
            jQuery( 'button#iloveimg_watermarkall .iloveimg-watermark-all__percent' ).width( '100%' );
            jQuery( 'button#iloveimg_watermarkall' ).addClass( 'is-finished' );
            batchInProgress = false;

            // Fire exactly ONE summary notice — no page reload.
            var status = batchErrors === 0 ? 'success' : ( batchSuccesses > 0 ? 'partial' : 'error' );
            var noticeMsg;
            if ( 'success' === status ) {
                /* translators: %d: number of images watermarked successfully */
                noticeMsg = sprintf( __( '%d image(s) watermarked successfully.', 'iloveimg-watermark' ), batchSuccesses );
            } else if ( 'partial' === status ) {
                /* translators: %1$d: number of successes, %2$d: number of failures */
                noticeMsg = sprintf( __( '%1$d image(s) watermarked, %2$d failed.', 'iloveimg-watermark' ), batchSuccesses, batchErrors );
            } else {
                /* translators: %d: number of images that failed to watermark */
                noticeMsg = sprintf( __( '%d image(s) failed to watermark.', 'iloveimg-watermark' ), batchErrors );
            }
            showAdminNotice( noticeMsg, 'error' === status ? 'error' : ( 'partial' === status ? 'warning' : 'success' ) );
        }
    }, 300 );
}
