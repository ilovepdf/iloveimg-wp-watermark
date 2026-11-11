import { _x } from '@wordpress/i18n';

/**
 * Create the component for the dialog box.
 *
 * @param {string} content content of the dialog box.
 * @param {string} title title of the dialog box.
 * @param {string} buttonActionText text for the action button.
 * @returns {string} HTML component of the dialog box.
 */
export const createDialogComponent = (content, title, buttonActionText) => {
    const dialogComponent = `<dialog id="iloveimg-watermark-restore-dialog" class="iloveimg-restore-dialog"><h2 class="iloveimg-title-dialog">${title}</h2>
                <p class="iloveimg-content-dialog">${content}</p>
                <div class="iloveimg-btn-groups">
                    <button id="iloveimg-watermark-dialog-aceptted" class="button-primary">${buttonActionText}</button>
                    <button id="iloveimg-watermark-dialog-close" class="button-secondary">${_x(
        'Cancel',
        'button dialog box',
        'iloveimg-watermark'
    )}</button>
                </div>
            </dialog>`;

    return dialogComponent;
};

/**
 * Show admin notice on dashboard.
 *
 * @param {string} message the message to show.
 * @param {string} type the type of message. Default: success
 * @returns {string} HTML component of the admin notice.
 */
export const showAdminNotice = (message, type = 'success') => {
    // Validate type against allowlist to prevent class-name injection.
    const ALLOWED_TYPES = [ 'success', 'error', 'warning', 'info' ];
    if ( ! ALLOWED_TYPES.includes( type ) ) {
        type = 'success';
    }

    // Build notice via DOM so message is never injected as raw HTML.
    const div = document.createElement( 'div' );
    div.className = `ipdf-notice ilovepdf-base__layout-flex notice notice-${ type } is-dismissible`;

    const figure = document.createElement( 'figure' );
    figure.className = 'ipdf-logo ilovepdf-base__layout-flex ilovepdf-base__layout-items--center';
    const img = document.createElement( 'img' );
    img.src = ( window.iloveimgWatermark && iloveimgWatermark.logoUrl ) || '';
    img.alt = 'logo iloveimg';
    figure.appendChild( img );

    const p = document.createElement( 'p' );
    p.textContent = message;

    const button = document.createElement( 'button' );
    button.type = 'button';
    button.className = 'notice-dismiss';
    const span = document.createElement( 'span' );
    span.className = 'screen-reader-text';
    span.textContent = 'Dismiss this notice.';
    button.appendChild( span );

    div.appendChild( figure );
    div.appendChild( p );
    div.appendChild( button );

    const container = document.querySelector(
        '#wpwrap #wpcontent #wpbody #wpbody-content > h1, #wpwrap #wpcontent #wpbody #wpbody-content > h2, #wpwrap #wpcontent #wpbody #wpbody-content'
    );
    container?.insertAdjacentElement( 'beforebegin', div );

    if (container) {
        setTimeout(() => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }, 500);

        const btnsCloseNotice = document.querySelectorAll('.is-dismissible .notice-dismiss');

        if (btnsCloseNotice) {
            btnsCloseNotice.forEach((btn) => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.currentTarget.parentNode.remove();
                });
            });
        }
    }
};
