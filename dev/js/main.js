import { watermarkImage, statusWatermark, watermarkAll } from './watermark';
import { initClearBackup, initRestoreAllFiles, initRestoreFile } from './common/backup';
import { initWatermarkPreview, initMediaLibrary } from './watermark/preview';
import { initBulkWatermarkQueue } from './bulk';

(function ($) {
    var adminpage = '';
    if (typeof window.adminpage !== 'undefined') {
        adminpage = window.adminpage;
    }
    var timesIntervals = {};

    // Initialize based on admin page
    switch (adminpage) {
        case 'upload-php':
            $(document).on('click', 'button.iloveimg-watermark', watermarkImage);

            // Check watermarking status — silent=true so page-load pollers never
            // emit per-file notices (prevents notice-race RC2).
            $('.iloveimg_watermarking').each(function (index, element) {
                timesIntervals['ref_' + index] = setInterval(function () {
                    statusWatermark(element, index, timesIntervals, true);
                }, 1000);
            });

            initBulkWatermarkQueue({ formId: 'posts-filter', checkboxName: 'media[]' });
            break;

        case 'media_page_iloveimg-media-watermark-page':
            $(document).on('click', 'button.iloveimg-watermark', watermarkImage);
            $(document).on('click', 'button#iloveimg_watermarkall', watermarkAll);

            // Check watermarking status — silent=true so page-load pollers never
            // emit per-file notices (prevents notice-race RC2).
            $('.iloveimg_watermarking').each(function (index, element) {
                timesIntervals['ref_' + index] = setInterval(function () {
                    statusWatermark(element, index, timesIntervals, true);
                }, 1000);
            });

            // Own-page bulk "Apply" queue.
            initBulkWatermarkQueue({ formId: 'images-filter', checkboxName: 'image[]' });
            break;

        case 'post-php':
            $(document).on('click', 'button.iloveimg-watermark', watermarkImage);
            $(document).on('click', 'button#iloveimg_watermarkall', watermarkAll);

            // Check watermarking status — silent=true so page-load pollers never
            // emit per-file notices (prevents notice-race RC2).
            $('.iloveimg_watermarking').each(function (index, element) {
                timesIntervals['ref_' + index] = setInterval(function () {
                    statusWatermark(element, index, timesIntervals, true);
                }, 1000);
            });
            break;
    }

    // Settings page: highlight save button on change
    $('.iloveimg_settings__options-container form input').on('change', function () {
        if (!$('.iloveimg_settings__options-container form .submit button').hasClass('need_saving')) {
            setTimeout(function () {
                $('.iloveimg_settings__options-container form .submit button').addClass('need_saving');
                setTimeout(function () {
                    $('.iloveimg_settings__options-container form .submit button').removeClass('need_saving');
                }, 5000);
            }, 1000);
        }
    });

    // Initialize backup functionality
    initRestoreAllFiles();
    initClearBackup();
    initRestoreFile();

    // Initialize watermark preview and media library
    initWatermarkPreview();
    initMediaLibrary();
})(jQuery);
