var batchInProgress = false;
// Module-level counters for bulk-batch status tracking (J2).
// Reset to 0 at the start of each batch in watermarkAll() / handleBulkAction().
var batchSuccesses = 0;
var batchErrors = 0;

/**
 * Detect whether an HTML fragment represents a completed watermark result.
 *
 * Keys off the `.iloveimg_sizes_watermarked` anchor class that
 * Ilove_Img_Wm_Resources::render_watermark_details() always emits (Resources.php ~line 356).
 * This is the render→JS coupling point (Risk R4): if that class is ever renamed, detection
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
 * Reload the current page carrying the watermark status query var so the
 * existing show_notices() branches fire after any completion (single click,
 * poll, or "Watermark all"). AJAX can't server-redirect, so the client builds
 * the URL — this keeps one notice mechanism for all paths.
 *
 * @param {'success'|'error'|'partial'} status
 */
function reloadWithStatus( status ) {
    var url = new URL( window.location.href );
    url.searchParams.set( 'iloveimg-bulk-watermark', status );
    window.location.href = url.toString();
}

/**
 * Watermark image via AJAX
 * @param {Event} event
 */
export function watermarkImage( event ) {
    var element = jQuery( event.target );
    var container = element.closest( 'td' );
    /* translators: Shown inline in the media column when watermarking fails or returns empty. */
    var failedMsg = wp.i18n.__( 'Watermarking failed. Please try again.', 'iloveimg-watermark' );

    element.attr( 'disabled', 'disabled' );
    // Spinner class coupling: the <img> in get_status_of_column() carries class="iloveimg-spinner"
    // so this selector matches it; using .next() scopes to siblings of the clicked button (F1/H1).
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
                // Non-empty body: the PHP handler ran and appended to the shared transient.
                // Show the in-place fragment first, then reload so show_notices() fires.
                container.html( data );
                if ( ! batchInProgress ) {
                    // Single click: reload with query var to surface the server-set notice.
                    reloadWithStatus( isDoneFragment( data ) ? 'success' : 'error' );
                } else {
                    // Bulk: do NOT reload per-row. Track outcome so the interval can compute
                    // the overall status when the last button disappears.
                    if ( isDoneFragment( data ) ) {
                        batchSuccesses++;
                    } else {
                        batchErrors++;
                    }
                }
            } else {
                // Empty body = JS-only failure (e.g. nonce mismatch); no server transient was set.
                // Decision (b): show inline message, no reload.
                if ( batchInProgress ) {
                    // Bulk: replace cell content so button is gone and the counter can reach 0.
                    batchErrors++;
                    container.html( jQuery( '<p>' ).addClass( 'iloveimg-inline-message' ).text( failedMsg ) );
                } else {
                    // Single: re-enable button for retry, show inline message.
                    element.removeAttr( 'disabled' );
                    element.next( '.iloveimg-spinner' ).hide();
                    container.find( '.iloveimg-inline-message' ).remove();
                    // Build as DOM node so translated text cannot inject HTML/JS (F7/L3/Sec).
                    container.append( jQuery( '<p>' ).addClass( 'iloveimg-inline-message' ).text( failedMsg ) );
                }
            }
        },
        error: function () {
            // Transport / HTTP error: decision (b) — inline message, no reload.
            if ( batchInProgress ) {
                // Bulk: replace cell so button is removed and the counter can reach 0.
                batchErrors++;
                container.html( jQuery( '<p>' ).addClass( 'iloveimg-inline-message' ).text( failedMsg ) );
            } else {
                // Single: re-enable button and surface a message; never blank the cell.
                element.removeAttr( 'disabled' );
                element.next( '.iloveimg-spinner' ).hide();
                container.find( '.iloveimg-inline-message' ).remove();
                // Build as DOM node so translated text cannot inject HTML/JS (F7/L3/Sec).
                container.append( jQuery( '<p>' ).addClass( 'iloveimg-inline-message' ).text( failedMsg ) );
            }
        },
    } );
}

/**
 * Check watermark status
 * @param {HTMLElement} element
 * @param {number} index
 * @param {Object} timesIntervals
 */
export function statusWatermark( element, index, timesIntervals ) {
    var $element = jQuery( element );
    var container = $element.closest( 'td' );
    /* translators: Shown inline in the media column when polling fails repeatedly due to network errors. */
    var failedMsg = wp.i18n.__( 'Watermarking failed. Please try again.', 'iloveimg-watermark' );

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
                // Non-empty body: server appended to the shared transient.
                // Reload so show_notices() surfaces the right top notice.
                if ( ! batchInProgress ) {
                    // 1200ms delay gives the done/error fragment a moment to render before reload.
                    reloadWithStatus( isDoneFragment( data ) ? 'success' : 'error' );
                } else {
                    // Bulk: track outcome so the interval can compute overall status.
                    if ( isDoneFragment( data ) ) {
                        batchSuccesses++;
                    } else {
                        batchErrors++;
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
            // the interval and surface an error so the spinner never spins indefinitely (F2/H2).
            var failCount = ( $element.data( 'iloveimg-fail-count' ) || 0 ) + 1;
            $element.data( 'iloveimg-fail-count', failCount );

            if ( failCount >= POLL_FAIL_THRESHOLD ) {
                clearInterval( timesIntervals[ 'ref_' + index ] );
                $element.data( 'iloveimg-fail-count', 0 );

                if ( batchInProgress ) {
                    // Bulk: replace cell so button is gone and counter can reach 0.
                    batchErrors++;
                    container.html( jQuery( '<p>' ).addClass( 'iloveimg-inline-message' ).text( failedMsg ) );
                } else {
                    // Single: re-enable button so the user can retry manually.
                    container.find( 'button.iloveimg-watermark' ).removeAttr( 'disabled' );
                    // Hide spinner (class coupling mirrors the img class set in get_status_of_column()).
                    container.find( '.iloveimg-spinner' ).hide();
                    // Surface failure message in the cell; build as DOM node (F7/L3/Sec).
                    container.find( '.iloveimg-inline-message' ).remove();
                    container.append( jQuery( '<p>' ).addClass( 'iloveimg-inline-message' ).text( failedMsg ) );
                }
            }
        },
    } );
}

/**
 * Handle watermark all button
 *
 * Bug A fix: create exactly ONE interval AFTER all per-row clicks are triggered in the .each()
 * loop — multiple intervals (one per row) would race each other and clear independently, making
 * the reload fire too early or multiple times.
 * Bug D fix: add is-processing class on start, update percent bar in the interval, add is-finished
 * and delay reload by 1200ms so the user sees the bar reach 100% before the page reloads.
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

    // Single interval monitors overall completion (Bug A: was inside .each(), creating N intervals).
    var timeReload = setInterval( function () {
        var _percent = 100 - ( jQuery( 'button.iloveimg-watermark' ).length * 100 ) / totalImagesToWatermark;
        jQuery( 'button#iloveimg_watermarkall .iloveimg-watermark-all__percent' ).width( _percent + '%' );

        if ( jQuery( 'button.iloveimg-watermark' ).length === 0 ) {
            clearInterval( timeReload );
            // Set bar to 100% and show finished state before reload.
            // 1200ms > the 1s CSS width transition so the user sees full completion.
            jQuery( 'button#iloveimg_watermarkall .iloveimg-watermark-all__percent' ).width( '100%' );
            jQuery( 'button#iloveimg_watermarkall' ).addClass( 'is-finished' );
            // Compute overall status from per-row counters and reload with the query var so
            // show_notices() can render per-file success/error notices after the full-page load.
            var status = batchErrors === 0 ? 'success' : ( batchSuccesses > 0 ? 'partial' : 'error' );
            setTimeout( function () {
                reloadWithStatus( status );
            }, 1200 );
        }
    }, 300 );
}

/**
 * Handle bulk action submit
 *
 * Bug A fix: create exactly ONE interval AFTER triggering all per-row clicks, not inside the
 * .each() loop — otherwise N intervals would race each other.
 *
 * @param {Event} event
 */
export function handleBulkAction( event ) {
    // Check both action selectors (top and bottom dropdowns)
    var selectedAction = jQuery( document ).find( 'select[name=action] option:checked' ).val() || jQuery( document ).find( 'select[name=action2] option:checked' ).val();

    if ( selectedAction === 'iloveimg_watermark' ) {
        event.preventDefault();
        batchInProgress = true;
        // Reset per-batch counters so a fresh run starts clean.
        batchSuccesses = 0;
        batchErrors = 0;
        var totalToProcess = jQuery( 'table.wp-list-table.images tbody tr, table.wp-list-table.media tbody tr' ).find( "th.check-column input[type='checkbox']:checked" ).length;

        // Trigger all per-row clicks first.
        jQuery( 'table.wp-list-table.images tbody tr, table.wp-list-table.media tbody tr' ).each( function (
            index,
            element
        ) {
            if ( jQuery( element ).find( "th.check-column input[type='checkbox']" ).is( ':checked' ) ) {
                jQuery( element ).find( 'button.iloveimg-watermark' ).trigger( 'click' );
            }
        } );

        // Single interval created AFTER the .each() loop (Bug A fix).
        // Checks for remaining buttons; a stuck or empty row that never had a button also
        // won't block this because we count only rows with the button (Bug B robustness).
        if ( totalToProcess > 0 ) {
            var timeReload = setInterval( function () {
                var remaining = jQuery( 'table.wp-list-table.images tbody tr, table.wp-list-table.media tbody tr' ).find( 'button.iloveimg-watermark' ).length;
                if ( remaining === 0 ) {
                    clearInterval( timeReload );
                    // Compute overall status from per-row counters and reload with the query var
                    // so show_notices() can render per-file success/error notices after the reload.
                    var status = batchErrors === 0 ? 'success' : ( batchSuccesses > 0 ? 'partial' : 'error' );
                    reloadWithStatus( status );
                }
            }, 500 );
        }
    }
}
