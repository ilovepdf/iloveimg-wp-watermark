/**
 * Shared row-status seam for the watermark plugin.
 *
 * Mirrors the compress plugin's dev/js/common/rowStatus.js so both plugin
 * designs stay structurally parallel. Watermark-specific additions:
 *   - applyOverviewCounts() (compress uses a separate AJAX refresh; watermark
 *     embeds the counts inline in the done fragment — the asymmetry is intentional).
 *
 * CONTRACT: `iloveimg_sizes_watermarked` is the shared completion class between
 * isDoneFragment() and the `class="thickbox iloveimg_sizes_watermarked"` anchor
 * emitted by Ilove_Img_Wm_Resources::render_watermark_details(). Never rename
 * on one side only.
 */

/**
 * Detect whether an HTML fragment represents a completed watermark result.
 *
 * Keys off the `.iloveimg_sizes_watermarked` anchor class that
 * Ilove_Img_Wm_Resources::render_watermark_details() always emits.
 *
 * @param {string} html
 * @returns {boolean}
 */
export function isDoneFragment( html ) {
    return typeof html === 'string' && html.indexOf( 'iloveimg_sizes_watermarked' ) !== -1;
}

/**
 * Swap the contents of a status <td> with a new HTML fragment.
 *
 * @param {jQuery} $td  The status table cell to update.
 * @param {string} html The HTML fragment returned by the AJAX endpoint.
 */
export function applyFragment( $td, html ) {
    $td.html( html );
}

/**
 * Render a client-side error badge into a status <td> and hide the spinner.
 * Does NOT re-enable the action button — the row stays in a stable failed state
 * and the user cannot retry from within the bulk run.
 *
 * Mirrors the ilovepdf-wp markRowFailed pattern: a purely client-side badge,
 * never injected as HTML, so nonce/re-bind problems from server HTML are avoided.
 *
 * @param {jQuery} $cell    The status table cell (<td>) to update.
 * @param {string} message  Plain-text error message; written via textContent, never innerHTML.
 */
export function markRowFailed( $cell, message ) {
	var badge = document.createElement( 'p' );
	badge.className = 'iloveimg-status-failed';
	badge.textContent = message;
	// empty() removes the disabled button and spinner; append() injects the badge.
	$cell.empty().append( badge );
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
export function applyOverviewCounts( html ) {
    var $overview = jQuery( '.iloveimg_settings__overview__statistics' );
    if ( ! $overview.length ) {
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
    $overview.find( '[data-iloveimg-wm-count]' ).text( watermarked );
    $overview.find( '[data-iloveimg-total-count]' ).text( total );
    var percent = total > 0 ? Math.round( ( watermarked / total ) * 100 ) : 0;
    $overview.find( '.iloveimg_percent-total' ).width( percent + '%' );
    $overview.find( '[data-iloveimg-wm-percent]' ).text( percent + '%' );
}
