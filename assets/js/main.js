// jQuery(function(){
//     alert('.field_'+ jQuery("#fieldrow-mode select").val())
//     jQuery('.field_'+ jQuery("#fieldrow-mode select").val()).show();
//     jQuery("#fieldrow-mode select").on("change", function(){
//         alert(jQuery("#fieldrow-mode select").val());
//     });
// });
(function() {
    var adminpage = '';
    if (typeof window.adminpage !== 'undefined') {
        adminpage = window.adminpage;
    }
    var timesIntervals = new Array();
    var timeReload;

    function compressImage(event) {
        var element = jQuery(event.target);
        var container = element.closest('td');

        element.attr('disabled', 'disabled');
        element.next().show();
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
              action: 'iLoveIMG_Watermark_library',
              id: element.data('id') || element.attr('data-id')
            },
            success: function(data) {
              element.removeAttr('disabled');
              container.html(data);
            },
            error: function() {
              element.removeAttr('disabled');
              //container.find('span.spinner').addClass('hidden');
            }
          });
    }

    function statusCompressing(element, index){
        var element = jQuery(element);
        var container = element.closest('td');
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
              action: 'iLoveIMG_Watermark_library_is_compressed',
              id: element.data('id') || element.attr('data-id')
            },
            success: function(data) {
                
                clearInterval(timesIntervals["ref_" + index]);
                container.html(data);
            },
            error: function() {

            }
          });
    }
    var totalImagesToCompress = 0;
    switch (adminpage) {
        case 'upload-php':
        case 'media_page_iloveimg-media-page':
        case 'post-php':
            jQuery(document).on("click", "button.iloveimg-compress", compressImage);
            jQuery(document).on("click", "button#iloveimg_allcompress", function(event){
                totalImagesToCompress = jQuery("button.iloveimg-compress").length;
                
                jQuery("button#iloveimg_allcompress").attr('disabled', 'disabled');
                jQuery("button.iloveimg-compress").each(function(index, element){
                    var buttonCompress = jQuery(element);
                    buttonCompress.trigger("click");
                    timeReload = setInterval(function(){
                        var _percent = ( 100 - (jQuery("button.iloveimg-compress").length*100)/totalImagesToCompress);
                        jQuery("button#iloveimg_allcompress .iloveimg-compress-all__percent").width( _percent + "%" );
                        if(!jQuery("button.iloveimg-compress").length){
                            clearInterval(timeReload);
                            location.reload();
                        }
                    }, 300)
                });
            });
            jQuery('<option>').val('iloveimg_bulk_action').text("Compress Images").appendTo('select[name=action]');
            jQuery('<option>').val('iloveimg_bulk_action').text("Compress Images").appendTo('select[name=action2]');
            jQuery('.iloveimg_watermarking').each(function(index, element) {
                timesIntervals["ref_" + index] = setInterval(function(){
                    statusCompressing(element, index);
                },  1000);
            });

            jQuery(document).on("submit", "form#images-filter, form#posts-filter", function(event){
                if(jQuery(document).find("select#bulk-action-selector-top option:checked").val() == 'iloveimg_bulk_action'){
                    event.preventDefault();
                    jQuery("table.wp-list-table.images tbody tr, table.wp-list-table.media tbody tr").each(function(index, element){
                        if(jQuery(element).find("th.check-column input[type='checkbox']").is(':checked')){
                            jQuery(element).find("td.status button, td.iloveimg_watermarkion button").trigger("click");
                        }
                    });
                }
            });
            break;
    }
    jQuery(".iloveimg_settings__options-container form input").on("change", function(element){
        if(!jQuery(".iloveimg_settings__options-container form .submit button").hasClass('need_saving')){
            setTimeout(function(){
                jQuery(".iloveimg_settings__options-container form .submit button").addClass('need_saving');
                setTimeout(function(){
                    jQuery(".iloveimg_settings__options-container form .submit button").removeClass('need_saving');
                }, 5000);
            }, 1000);
        }
    });

    var frame;
    jQuery("#media-open").on("click", function(event){
        event.preventDefault();
        if ( frame ) {
          frame.open();
          return;
        }
        
        // Create a new media frame
        frame = wp.media({
          title: 'Select or Upload Media',
          button: {
            text: 'Select Watermark'
          },
          multiple: false  // Set to true to allow multiple files to be selected
        });
        

        frame.on( 'select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            console.log(attachment);
            jQuery("input[name='iloveimg_field_image']").val(attachment.url)
        });

        frame.open();
    });




}).call();