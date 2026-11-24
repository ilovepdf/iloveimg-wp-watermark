import { __, sprintf } from '@wordpress/i18n';
import { showAdminNotice } from '../components';
import { runQueue } from './queue';
import { applyFragment, applyOverviewCounts, markRowFailed } from '../common/rowStatus';

/**
 * Initialize the bulk watermark queue for a media list table.
 *
 * Binds to the given form's submit event and intercepts the
 * `iloveimg_watermark` bulk action, processing selected attachments through
 * the bounded concurrent pool instead of a standard form GET.
 *
 * @param {object} config
 * @param {string} config.formId        The id of the form element to bind to.
 * @param {string} config.checkboxName  The checkbox input name to collect IDs from.
 */
export function initBulkWatermarkQueue( config ) {
	var formId = ( config && config.formId ) ? config.formId : 'posts-filter';
	var checkboxName = ( config && config.checkboxName ) ? config.checkboxName : 'media[]';

	var form = document.getElementById( formId );
	if ( ! form ) {
		return;
	}

	form.addEventListener( 'submit', function ( event ) {
		// Determine the active bulk action — either the top or bottom selector.
		var select1 = form.querySelector( 'select[name="action"]' );
		var select2 = form.querySelector( 'select[name="action2"]' );
		var activeAction = '';
		if ( select1 && select1.value !== '-1' ) {
			activeAction = select1.value;
		} else if ( select2 && select2.value !== '-1' ) {
			activeAction = select2.value;
		}

		if ( activeAction !== 'iloveimg_watermark' ) {
			return; // Not our action — let WP handle it normally.
		}

		var ids = Array.prototype.slice.call(
			form.querySelectorAll( 'input[name="' + checkboxName + '"]:checked' )
		).map( function ( cb ) {
			return cb.value;
		} );

		// No items selected — let WP's native "no items selected" flow proceed.
		if ( ids.length === 0 ) {
			return;
		}

		event.preventDefault();

		var nonce = ( window.iloveimgWatermark && iloveimgWatermark.nonce ) ? iloveimgWatermark.nonce : '';

		// Insert progress banner immediately after .wp-header-end (the standard WP
		// admin hook point for notices injected above the list table).
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

		// Pre-seed the banner text before the queue starts so the user sees progress
		// text immediately (before the first file settles).
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
		}

		function onFileDone( id, ok, html ) {
			// Locate the row's status <td> via the per-row button's data-id.
			var $btn = jQuery( 'button.iloveimg-watermark[data-id="' + id + '"]' );
			if ( ok ) {
				// Terminal success: swap in the server-rendered done fragment and update counts.
				if ( html && $btn.length ) {
					applyFragment( $btn.closest( 'td' ), html );
					applyOverviewCounts( html );
				}
			} else {
				// Terminal failure: show a persistent error badge in the row.
				if ( $btn.length ) {
					markRowFailed(
						$btn.closest( 'td' ),
						__( 'Watermarking failed. Please try again.', 'iloveimg-watermark' )
					);
				}
			}
		}

		function onFileStart( id ) {
			// Disable the row button and show its spinner immediately before the
			// trigger request is issued, giving the user instant visual feedback.
			jQuery( 'button.iloveimg-watermark[data-id="' + id + '"]' )
				.attr( 'disabled', 'disabled' )
				.next( '.iloveimg-spinner' )
				.show();
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
			console.error( '[iloveimg wm bulk]', err );
			showAdminNotice(
				__( 'Bulk watermark failed unexpectedly. Please try again.', 'iloveimg-watermark' ),
				'error'
			);
		} );
	} );
}
