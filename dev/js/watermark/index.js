/**
 * Watermark image via AJAX
 * @param {Event} event
 */
export function watermarkImage(event) {
    var element = jQuery(event.target);
    var container = element.closest('td');

    element.attr('disabled', 'disabled');
    element.next('.spinner, .loading').show();

    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'ilove_img_wm_library',
            id: element.data('id') || element.attr('data-id'),
            imgnonce: element.data('imgnonce') || element.attr('data-imgnonce'),
        },
        success: function (data) {
            element.removeAttr('disabled');
            container.html(data);
        },
        error: function () {
            element.removeAttr('disabled');
        },
    });
}

/**
 * Check watermark status
 * @param {HTMLElement} element
 * @param {number} index
 * @param {Object} timesIntervals
 */
export function statusWatermark(element, index, timesIntervals) {
    var $element = jQuery(element);
    var container = $element.closest('td');

    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'ilove_img_wm_library_is_watermarked',
            id: $element.data('id') || $element.attr('data-id'),
            imgnonce: $element.data('imgnonce') || $element.attr('data-imgnonce'),
        },
        success: function (data) {
            clearInterval(timesIntervals['ref_' + index]);
            container.html(data);
        },
        error: function () { },
    });
}

/**
 * Handle watermark all button
 * @param {Event} event
 */
export function watermarkAll(event) {
    var totalImagesToWatermark = jQuery('button.iloveimg-watermark').length;
    var timeReload;

    jQuery('button#iloveimg_watermarkall').attr('disabled', 'disabled');
    jQuery('button.iloveimg-watermark').each(function (index, element) {
        var buttonWatermark = jQuery(element);
        buttonWatermark.trigger('click');
        timeReload = setInterval(function () {
            var _percent = 100 - (jQuery('button.iloveimg-watermark').length * 100) / totalImagesToWatermark;
            jQuery('button#iloveimg_watermarkall .iloveimg-watermark-all__percent').width(_percent + '%');
            if (!jQuery('button.iloveimg-watermark').length) {
                clearInterval(timeReload);
                location.reload();
            }
        }, 300);
    });
}

/**
 * Handle bulk action submit
 * @param {Event} event
 */
export function handleBulkAction(event) {
    // Check both action selectors (top and bottom dropdowns)
    var selectedAction = jQuery(document).find('select[name=action] option:checked').val() || jQuery(document).find('select[name=action2] option:checked').val();

    if (selectedAction === 'iloveimg_watermark') {
        event.preventDefault();
        var totalToProcess = jQuery('table.wp-list-table.images tbody tr, table.wp-list-table.media tbody tr').find("th.check-column input[type='checkbox']:checked").length;
        var timeReload;
        
        jQuery('table.wp-list-table.images tbody tr, table.wp-list-table.media tbody tr').each(function (
            index,
            element
        ) {
            if (jQuery(element).find("th.check-column input[type='checkbox']").is(':checked')) {
                jQuery(element).find('button.iloveimg-watermark').trigger('click');
            }
        });
        
        if (totalToProcess > 0) {
            timeReload = setInterval(function () {
                var remaining = jQuery('table.wp-list-table.images tbody tr, table.wp-list-table.media tbody tr').find('button.iloveimg-watermark').length;
                if (remaining === 0) {
                    clearInterval(timeReload);
                    location.reload();
                }
            }, 500);
        }
    }
}
