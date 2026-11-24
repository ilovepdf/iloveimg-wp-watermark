import { __, sprintf } from '@wordpress/i18n';
import { showAdminNotice } from '../components';
import { isDoneFragment, applyFragment, applyOverviewCounts, markRowFailed } from '../common/rowStatus';
import { runQueue, pollUntilSettled } from '../bulk/queue';

// Per page-load set of attachment ids that have already received a success notice.
// Prevents stacking duplicate "Image watermarked successfully" notices when the
// same id resolves to a done fragment more than once (e.g. trigger + poller both fire).
const notifiedIds = new Set();

/**
 * Attach a setInterval poller to a newly-injected .iloveimg_watermarking element.
 *
 * Called after any applyFragment() swap that re-injects a polling element
 * (queued/status=3 or still-processing/status=1) so the row resolves without a
 * manual page reload (W5 — re-bind fix for status=3 hang).
 *
 * @param {HTMLElement} element  The .iloveimg_watermarking DOM node.
 * @param {boolean}     silent   When true, the poller never emits per-file notices.
 *                               Pass true for page-load pollers; false for single-click.
 */
function attachPollerToElement( element, silent ) {
    var newTimesIntervals = {};
    var newIndex = 'swap_' + Date.now() + '_' + Math.floor( Math.random() * 10000 );
    newTimesIntervals[ 'ref_' + newIndex ] = setInterval( function () {
        statusWatermark( element, newIndex, newTimesIntervals, silent );
    }, 1000 );
}

/**
 * Watermark image via AJAX (single-file click path).
 *
 * On AJAX success: applies the returned fragment; re-binds poller if needed.
 * On AJAX error (transport / HTTP error): falls back to polling the status endpoint
 * rather than showing an immediate failure notice — the server may have already
 * started the job and will complete it past the proxy timeout. Polling through to
 * the true outcome prevents a false failure notice and an accidental duplicate trigger.
 *
 * @param {Event} event
 */
export function watermarkImage( event ) {
    var element = jQuery( event.target );
    var container = element.closest( 'td' );
    var id = element.data( 'id' ) || element.attr( 'data-id' );
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
                applyFragment( container, data );
                // Re-bind poller if the swap re-injected a polling element (queued/status=3 fix).
                // Single-click path: silent=false so the resolved poller can still show notices.
                var $newPolling = container.find( '.iloveimg_watermarking' );
                if ( $newPolling.length ) {
                    attachPollerToElement( $newPolling[ 0 ], false );
                } else {
                    // Single-file: show a dismissible notice — no page reload.
                    if ( isDoneFragment( data ) ) {
                        applyOverviewCounts( data );
                        if ( ! notifiedIds.has( id ) ) {
                            notifiedIds.add( id );
                            showAdminNotice( __( 'Image watermarked successfully.', 'iloveimg-watermark' ), 'success' );
                        }
                    } else {
                        showAdminNotice( failedMsg, 'error' );
                    }
                }
            } else {
                // Empty body from trigger (should be rare): poll through to the true outcome
                // rather than immediately failing, to stay consistent with the error handler.
                var pollNonce = ( window.iloveimgWatermark && iloveimgWatermark.nonce )
                    ? iloveimgWatermark.nonce
                    : ( element.data( 'imgnonce' ) || element.attr( 'data-imgnonce' ) );
                pollUntilSettled( id, pollNonce, 'ilove_img_wm_library_is_watermarked' )
                    .then( function ( html ) {
                        applyFragment( container, html );
                        if ( isDoneFragment( html ) ) {
                            applyOverviewCounts( html );
                            if ( ! notifiedIds.has( id ) ) {
                                notifiedIds.add( id );
                                showAdminNotice( __( 'Image watermarked successfully.', 'iloveimg-watermark' ), 'success' );
                            }
                        } else {
                            element.removeAttr( 'disabled' );
                            element.next( '.iloveimg-spinner' ).hide();
                            showAdminNotice( failedMsg, 'error' );
                        }
                    } )
                    .catch( function () {
                        element.removeAttr( 'disabled' );
                        element.next( '.iloveimg-spinner' ).hide();
                        showAdminNotice( failedMsg, 'error' );
                    } );
            }
        },
        error: function () {
            // Transport / HTTP error: the server may have already started the job and
            // will complete it past the proxy timeout (ignore_user_abort ensures this).
            // Poll through to the true outcome instead of showing an immediate failure
            // notice — this prevents a false red badge and an accidental duplicate trigger.
            var pollNonce = ( window.iloveimgWatermark && iloveimgWatermark.nonce )
                ? iloveimgWatermark.nonce
                : ( element.data( 'imgnonce' ) || element.attr( 'data-imgnonce' ) );
            pollUntilSettled( id, pollNonce, 'ilove_img_wm_library_is_watermarked' )
                .then( function ( html ) {
                    applyFragment( container, html );
                    if ( isDoneFragment( html ) ) {
                        applyOverviewCounts( html );
                        if ( ! notifiedIds.has( id ) ) {
                            notifiedIds.add( id );
                            showAdminNotice( __( 'Image watermarked successfully.', 'iloveimg-watermark' ), 'success' );
                        }
                    } else {
                        element.removeAttr( 'disabled' );
                        element.next( '.iloveimg-spinner' ).hide();
                        showAdminNotice( failedMsg, 'error' );
                    }
                } )
                .catch( function () {
                    element.removeAttr( 'disabled' );
                    element.next( '.iloveimg-spinner' ).hide();
                    showAdminNotice( failedMsg, 'error' );
                } );
        },
    } );
}

/**
 * Check watermark status (page-load interval poller callback).
 *
 * Runs on .iloveimg_watermarking elements present at page load (status 1 or 3).
 * With the new poll-handler contract, the server now returns HTTP 202 (empty body)
 * for in-flight rows instead of 500 — jQuery.ajax fires success with an empty string,
 * which the existing empty-body guard no-ops correctly, so polling continues unchanged.
 *
 * @param {HTMLElement} element
 * @param {number|string} index
 * @param {Object} timesIntervals
 * @param {boolean} [silent=false]  When true, suppresses per-file admin notices.
 *                                  Set to true for page-load pollers to avoid the
 *                                  notice-race (RC2) where pollers resolving after
 *                                  a watermarkAll batch ends fire spurious notices.
 */
export function statusWatermark( element, index, timesIntervals, silent ) {
    var $element = jQuery( element );
    // Stop orphaned pollers: if this poller's row was already swapped out by an
    // applyFragment() update, the node is detached — its closest('td') is empty so
    // updates no-op, but a success/failure notice would still fire globally. Clear and bail.
    if ( ! document.body.contains( element ) ) {
        clearInterval( timesIntervals[ 'ref_' + index ] );
        return;
    }
    var pollId = $element.data( 'id' ) || $element.attr( 'data-id' );
    var container = $element.closest( 'td' );
    /* translators: Shown in admin notice when watermarking polling fails repeatedly. */
    var failedMsg = __( 'Watermarking failed. Please try again.', 'iloveimg-watermark' );

    // Maximum consecutive non-202 transport failures before we give up polling for this row.
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
            // HTTP 202 from the server goes to this success callback with an empty string
            // (jQuery treats 2xx as success). The empty-body guard below keeps polling.
            // Guard: never blank the <td> on an empty or whitespace-only response.
            if ( typeof data === 'string' && data.trim() !== '' ) {
                clearInterval( timesIntervals[ 'ref_' + index ] );

                // Inspect the response before applying it: if it re-injects a polling
                // element (queued/status=3 fix) always apply and re-bind, regardless of
                // batch mode — the row is still in-flight and must keep polling.
                var $parsed = jQuery( '<div>' ).html( data );
                var hasPolling = $parsed.find( '.iloveimg_watermarking' ).length > 0;

                if ( hasPolling ) {
                    // Response contains a new polling element: apply and re-bind.
                    // Propagate silent flag so re-bound pollers stay in the same mode.
                    applyFragment( container, data );
                    var $rebound = container.find( '.iloveimg_watermarking' );
                    if ( $rebound.length ) {
                        attachPollerToElement( $rebound[ 0 ], silent );
                    }
                } else {
                    // Poll resolved to a terminal state (done badge or failure fragment).
                    applyFragment( container, data );
                    var done = isDoneFragment( data );
                    if ( done ) {
                        applyOverviewCounts( data );
                        if ( ! silent ) {
                            if ( ! notifiedIds.has( pollId ) ) {
                                notifiedIds.add( pollId );
                                showAdminNotice( __( 'Image watermarked successfully.', 'iloveimg-watermark' ), 'success' );
                            }
                        }
                    } else if ( ! silent ) {
                        showAdminNotice( failedMsg, 'error' );
                    }
                }
            }
            // If empty: leave the existing cell untouched and keep polling.
        },
        error: function ( jqXHR ) {
            // Any non-2xx status is a real transport failure (network down, 502/504, DNS).
            // HTTP 202 no longer applies here — the server now uses wp_die() with response:202
            // which jQuery receives as a 2xx success with empty body (handled above).

            // Count consecutive transport failures per row (stored on the element via jQuery .data()
            // so separate rows do not share a counter). After POLL_FAIL_THRESHOLD failures we stop
            // the interval and surface an error so the spinner never spins indefinitely.
            var failCount = ( $element.data( 'iloveimg-fail-count' ) || 0 ) + 1;
            $element.data( 'iloveimg-fail-count', failCount );

            if ( failCount >= POLL_FAIL_THRESHOLD ) {
                clearInterval( timesIntervals[ 'ref_' + index ] );
                $element.data( 'iloveimg-fail-count', 0 );

                // Single-file: re-enable button so the user can retry manually.
                container.find( 'button.iloveimg-watermark' ).removeAttr( 'disabled' );
                // Hide spinner (class coupling mirrors the img class set in get_status_of_column()).
                container.find( '.iloveimg-spinner' ).hide();
                if ( ! silent ) {
                    showAdminNotice( failedMsg, 'error' );
                }
            }

            void jqXHR; // consumed above; suppress unused-var linters
        },
    } );
}

/**
 * Handle "Watermark All" button on the overview page.
 *
 * Collects all unwatermarked image ids visible on the page and submits them to
 * the same bounded concurrent pool runner used by the native bulk-action path
 * (runQueue from bulk/queue.js).  Provides the same row-feedback, progress banner,
 * overview-count, and end summary as the bulk action — there is now a single bulk
 * code path for both entry points.
 *
 * @param {Event} event
 */
export function watermarkAll( event ) {
    // Collect eligible ids from all watermark buttons currently visible on the page.
    var ids = [];
    jQuery( 'button.iloveimg-watermark' ).each( function () {
        var id = jQuery( this ).data( 'id' ) || jQuery( this ).attr( 'data-id' );
        if ( id ) {
            ids.push( String( id ) );
        }
    } );

    if ( ids.length === 0 ) {
        return;
    }

    jQuery( 'button#iloveimg_watermarkall' ).attr( 'disabled', 'disabled' ).addClass( 'is-processing' );
    notifiedIds.clear();

    var nonce = ( window.iloveimgWatermark && iloveimgWatermark.nonce ) ? iloveimgWatermark.nonce : '';

    // Progress banner — same markup as the bulk-action path.
    var logoUrl = ( window.iloveimgWatermark && iloveimgWatermark.logoUrl ) ? iloveimgWatermark.logoUrl : '';
    var $figure = jQuery( '<figure class="ipdf-logo ilovepdf-base__layout-flex ilovepdf-base__layout-items--center"></figure>' );
    $figure.append( jQuery( '<img>' ).attr( 'src', logoUrl ).attr( 'alt', 'logo iloveimg' ) );
    var $banner = jQuery( '<div id="iloveimg-bulk-progress-banner" class="ipdf-notice ilovepdf-base__layout-flex notice notice-info ipdf-bulk-progress"></div>' );
    $banner.append( $figure ).append( jQuery( '<p></p>' ) );
    var $headerEnd = jQuery( '.wp-header-end' );
    if ( $headerEnd.length ) {
        $banner.insertAfter( $headerEnd );
    } else {
        jQuery( '#wpbody-content' ).prepend( $banner );
    }
    var $bannerText = $banner.find( 'p' );

    /* translators: %1$d: current file number being processed, %2$d: total files */
    $bannerText.text(
        sprintf(
            __( 'Watermarking file %1$d of %2$d', 'iloveimg-watermark' ),
            0,
            ids.length
        )
    );

    function onProgress( progress ) {
        /* translators: %1$d: current file number being processed, %2$d: total files */
        $bannerText.text(
            sprintf(
                __( 'Watermarking file %1$d of %2$d', 'iloveimg-watermark' ),
                progress.done,
                progress.total
            )
        );
        // Keep the button's internal progress bar in sync for visual feedback.
        var percent = progress.total > 0
            ? Math.round( ( progress.done / progress.total ) * 100 )
            : 0;
        jQuery( 'button#iloveimg_watermarkall .iloveimg-watermark-all__percent' ).width( percent + '%' );
    }

    function onFileStart( id ) {
        jQuery( 'button.iloveimg-watermark[data-id="' + id + '"]' )
            .attr( 'disabled', 'disabled' )
            .next( '.iloveimg-spinner' )
            .show();
    }

    function onFileDone( id, ok, html ) {
        var $btn = jQuery( 'button.iloveimg-watermark[data-id="' + id + '"]' );
        if ( ok ) {
            if ( html && $btn.length ) {
                applyFragment( $btn.closest( 'td' ), html );
                applyOverviewCounts( html );
            }
        } else {
            if ( $btn.length ) {
                markRowFailed(
                    $btn.closest( 'td' ),
                    __( 'Watermarking failed. Please try again.', 'iloveimg-watermark' )
                );
            }
        }
    }

    runQueue( {
        ids: ids,
        action: 'ilove_img_wm_library',
        pollAction: 'ilove_img_wm_library_is_watermarked',
        nonce: nonce,
        onProgress: onProgress,
        onFileStart: onFileStart,
        onFileDone: onFileDone,
    } ).then( function ( result ) {
        $banner.remove();
        jQuery( 'button#iloveimg_watermarkall .iloveimg-watermark-all__percent' ).width( '100%' );
        jQuery( 'button#iloveimg_watermarkall' ).removeClass( 'is-processing' ).addClass( 'is-finished' );

        var succeeded = result.succeeded;
        var failed    = result.failed;
        var noticeType;
        var noticeMsg;

        if ( failed === 0 ) {
            noticeType = 'success';
            /* translators: %d: number of images watermarked successfully */
            noticeMsg = sprintf(
                __( '%d image(s) watermarked successfully.', 'iloveimg-watermark' ),
                succeeded
            );
        } else {
            noticeType = 'warning';
            /* translators: %1$d: number of images watermarked successfully, %2$d: number of images that failed */
            noticeMsg = sprintf(
                __( '%1$d watermarked, %2$d failed.', 'iloveimg-watermark' ),
                succeeded,
                failed
            );
        }

        showAdminNotice( noticeMsg, noticeType );
    } ).catch( function ( err ) {
        $banner.remove();
        jQuery( 'button#iloveimg_watermarkall' ).removeClass( 'is-processing' ).removeAttr( 'disabled' );
        console.error( '[iloveimg wm watermarkAll]', err );
        showAdminNotice(
            __( 'Bulk watermark failed unexpectedly. Please try again.', 'iloveimg-watermark' ),
            'error'
        );
    } );
}
