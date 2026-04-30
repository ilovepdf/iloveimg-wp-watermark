/**
 * Initialize watermark preview functionality
 */
export const initWatermarkPreview = () => {
	if (!document.getElementById('iloveimg_settings__watermark__preview')) {
		return;
	}

	// Initialize Spectrum color picker
	jQuery('#picker').spectrum({
		showPaletteOnly: true,
		togglePaletteOnly: true,
		togglePaletteMoreText: 'more',
		togglePaletteLessText: 'less',
		palette: [
			['#000', '#444', '#666', '#999', '#ccc', '#eee', '#f3f3f3', '#fff'],
			['#f00', '#f90', '#ff0', '#0f0', '#0ff', '#00f', '#90f', '#f0f'],
			['#f4cccc', '#fce5cd', '#fff2cc', '#d9ead3', '#d0e0e3', '#cfe2f3', '#d9d2e9', '#ead1dc'],
			['#ea9999', '#f9cb9c', '#ffe599', '#b6d7a8', '#a2c4c9', '#9fc5e8', '#b4a7d6', '#d5a6bd'],
			['#e06666', '#f6b26b', '#ffd966', '#93c47d', '#76a5af', '#6fa8dc', '#8e7cc3', '#c27ba0'],
			['#c00', '#e69138', '#f1c232', '#6aa84f', '#45818e', '#3d85c6', '#674ea7', '#a64d79'],
			['#900', '#b45f06', '#bf9000', '#38761d', '#134f5c', '#0b5394', '#351c75', '#741b47'],
			['#600', '#783f04', '#7f6000', '#274e13', '#0c343d', '#073763', '#20124d', '#4c1130'],
		],
		change: function (color) {
			jQuery('#iloveimg_field_text_color').val(color);
			changeTextStyle();
		},
	});

	const changeTextStyle = function () {
		var fontFamily = jQuery('#iloveimg_field_text_family').val();
		var fontWeight = jQuery('#iloveimg_field_text_bold').is(':checked') ? 'bold' : 'normal';
		var fontStyle = jQuery('#iloveimg_field_text_italic').is(':checked') ? 'italic' : 'normal';
		var fontDecoration = jQuery('#iloveimg_field_text_underline').is(':checked') ? 'underline' : 'none';
		var fontColor = jQuery('#iloveimg_field_text_color').val() ? jQuery('#iloveimg_field_text_color').val() : '#000';

		if (['Courier New', 'Comic Sans MS', 'WenQuanYi Zen Hei', 'Lohit Marathi', 'Impact'].indexOf(fontFamily) > -1) {
			jQuery('.iloveimg_font_none_style').show();
			jQuery('#iloveimg_field_text_bold, #iloveimg_field_text_italic').attr('disabled', 'disabled');
		} else {
			jQuery('.iloveimg_font_none_style').hide();
			jQuery('#iloveimg_field_text_bold, #iloveimg_field_text_italic').removeAttr('disabled');
		}

		jQuery('#iloveimg_settings__watermark__preview p').text(jQuery('#iloveimg_field_text').val());
		jQuery('#iloveimg_settings__watermark__preview p').css({
			opacity: jQuery('#iloveimg_field_opacity').val() / 100,
			transform: 'rotate(' + jQuery('#iloveimg_field_rotation').val() + 'deg)',
			'font-weight': fontWeight,
			'font-style': fontStyle,
			'text-decoration': fontDecoration,
			'font-family': fontFamily,
			color: fontColor,
		});

		jQuery('#iloveimg_settings__watermark__preview img').css({
			opacity: jQuery('#iloveimg_field_opacity').val() / 100,
			transform: 'rotate(' + jQuery('#iloveimg_field_rotation').val() + 'deg)',
		});
	};

	const resizeFont = function () {
		if (
			jQuery('#iloveimg_field_text').val() &&
			jQuery('#iloveimg_field_scale').val() >= 0 &&
			jQuery('#iloveimg_field_scale').val() <= 100
		) {
			var maxWidth = jQuery('#iloveimg_settings__watermark__preview').width() * (jQuery('#iloveimg_field_scale').val() / 100);
			jQuery('#iloveimg_settings__watermark__preview p').css({
				fontSize: 0,
			});
			for (var i = 0; jQuery('#iloveimg_settings__watermark__preview p').outerWidth() < maxWidth; i++) {
				jQuery('#iloveimg_settings__watermark__preview p').css({
					fontSize: i,
				});
			}
			jQuery('#iloveimg_settings__watermark__preview p').css({
				fontSize: i - 2,
				'line-height': i - 2 + 'px',
			});
			jQuery('#iloveimg_settings__watermark__preview img').width(maxWidth);
		}
	};

	const changePosition = function () {
		var pos_h = 'calc(50% - ' + jQuery('#iloveimg_settings__watermark__preview p').outerWidth() / 2 + 'px)';
		var pos_v = 'calc(50% - ' + jQuery('#iloveimg_settings__watermark__preview p').outerHeight() / 2 + 'px)';

		jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-texts p')
			.eq(0)
			.css({ top: '0px', bottom: 'auto', left: '0px', right: 'auto' });
		jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-texts p')
			.eq(1)
			.css({ top: '0px', bottom: 'auto', left: pos_h, right: 'auto' });
		jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-texts p')
			.eq(2)
			.css({ top: '0px', bottom: 'auto', left: 'auto', right: '0px' });
		jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-texts p')
			.eq(3)
			.css({ top: pos_v, bottom: 'auto', left: '0px', right: 'auto' });
		jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-texts p')
			.eq(4)
			.css({ top: pos_v, bottom: 'auto', left: pos_h, right: 'auto' });
		jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-texts p')
			.eq(5)
			.css({ top: pos_v, bottom: 'auto', left: 'auto', right: '0px' });
		jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-texts p')
			.eq(6)
			.css({ top: 'auto', bottom: '0px', left: '0px', right: 'auto' });
		jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-texts p')
			.eq(7)
			.css({ top: 'auto', bottom: '0px', left: pos_h, right: 'auto' });
		jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-texts p')
			.eq(8)
			.css({ top: 'auto', bottom: '0px', left: 'auto', right: '0px' });

		pos_h = 'calc(50% - ' + jQuery('#iloveimg_settings__watermark__preview img').outerWidth() / 2 + 'px)';
		pos_v = 'calc(50% - ' + jQuery('#iloveimg_settings__watermark__preview img').outerHeight() / 2 + 'px)';

		jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-images img')
			.eq(0)
			.css({ top: '0px', bottom: 'auto', left: '0px', right: 'auto' });
		jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-images img')
			.eq(1)
			.css({ top: '0px', bottom: 'auto', left: pos_h, right: 'auto' });
		jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-images img')
			.eq(2)
			.css({ top: '0px', bottom: 'auto', left: 'auto', right: '0px' });
		jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-images img')
			.eq(3)
			.css({ top: pos_v, bottom: 'auto', left: '0px', right: 'auto' });
		jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-images img')
			.eq(4)
			.css({ top: pos_v, bottom: 'auto', left: pos_h, right: 'auto' });
		jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-images img')
			.eq(5)
			.css({ top: pos_v, bottom: 'auto', left: 'auto', right: '0px' });
		jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-images img')
			.eq(6)
			.css({ top: 'auto', bottom: '0px', left: '0px', right: 'auto' });
		jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-images img')
			.eq(7)
			.css({ top: 'auto', bottom: '0px', left: pos_h, right: 'auto' });
		jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-images img')
			.eq(8)
			.css({ top: 'auto', bottom: '0px', left: 'auto', right: '0px' });

		if (jQuery('#iloveimg_field_mosaic').is(':checked')) {
			jQuery('#iloveimg_settings__watermark__preview p, #iloveimg_settings__watermark__preview img').css({ visibility: 'visible' });
			jQuery('table.iloveimg_watermark_position').addClass('mode_mosaic');
		} else {
			jQuery('input:radio[name=iloveimg_field_position]').removeAttr('disabled');
			jQuery('table.iloveimg_watermark_position').removeClass('mode_mosaic');
			jQuery('#iloveimg_settings__watermark__preview p, #iloveimg_settings__watermark__preview img').css({ visibility: 'hidden' });
			jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-texts p')
				.eq(parseInt(jQuery('input:radio[name=iloveimg_field_position]:checked').val()) - 1)
				.css({ visibility: 'visible' });
			jQuery('#iloveimg_settings__watermark__preview .iloveimg_settings__watermark__preview-images img')
				.eq(parseInt(jQuery('input:radio[name=iloveimg_field_position]:checked').val()) - 1)
				.css({ visibility: 'visible' });
		}
	};

	// Initialize type selection
	jQuery('.iloveimg_settings__options__field__cols__2-' + jQuery('#iloveimg_field_type input:checked').val()).show();
	if (jQuery('#iloveimg_field_type input:checked').val() == 'text') {
		jQuery('#iloveimg_settings__watermark__preview img').hide();
		jQuery('#iloveimg_settings__watermark__preview p').show();
	} else {
		jQuery('#iloveimg_settings__watermark__preview p').hide();
		jQuery('#iloveimg_settings__watermark__preview img').show();
	}

	// Type change handler
	jQuery('#iloveimg_field_type input').on('change', function (event) {
		jQuery('.iloveimg_settings__options__field__cols__2-text,.iloveimg_settings__options__field__cols__2-image').hide();
		jQuery('.iloveimg_settings__options__field__cols__2-' + jQuery('#iloveimg_field_type input:checked').val()).show();
		if (jQuery('#iloveimg_field_type input:checked').val() == 'text') {
			jQuery('#iloveimg_settings__watermark__preview img').hide();
			jQuery('#iloveimg_settings__watermark__preview p').show();
		} else {
			jQuery('#iloveimg_settings__watermark__preview p').hide();
			jQuery('#iloveimg_settings__watermark__preview img').show();
		}
	});

	// Event handlers
	jQuery(".iloveimg_settings__options__field-preview input[type='text'], .iloveimg_settings__options__field-preview input[type='number']").on(
		'keyup change',
		function (element) {
			changeTextStyle();
			resizeFont();
			changePosition();
		}
	);

	jQuery('.iloveimg_settings__options__field-preview select').on('change', function (element) {
		changeTextStyle();
		resizeFont();
		changePosition();
	});

	jQuery(".iloveimg_settings__options__field-preview input[type='radio']").on('change', function (element) {
		changeTextStyle();
		resizeFont();
		changePosition();
	});

	jQuery(".iloveimg_settings__options__field-preview input[type='checkbox']").on('change', function (element) {
		if (jQuery('#iloveimg_field_mosaic').is(':checked')) {
			if (jQuery('#iloveimg_field_scale').val() > 33) {
				jQuery('#iloveimg_field_scale').val(33);
			}
		}
		changeTextStyle();
		resizeFont();
		changePosition();
	});

	// Initialize preview
	changeTextStyle();
	resizeFont();
	changePosition();
};

/**
 * Initialize media library functionality
 */
export const initMediaLibrary = () => {
	// Image input change handler
	jQuery("input[name='iloveimg_field_image']").on('keyup change', function (event) {
		jQuery('#iloveimg_settings__watermark__preview img').attr('src', jQuery("input[name='iloveimg_field_image']").val());
	});

	// Media library open button
	var frame;
	jQuery('#media-open').on('click', function (event) {
		event.preventDefault();

		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ilove_img_wm_library_set_watermark_image',
			},
			success: function (data) {},
		});

		if (frame) {
			frame.open();
			return;
		}

		// Create a new media frame
		frame = wp.media({
			title: 'Select or Upload Media',
			button: {
				text: 'Select Watermark',
			},
			multiple: false,
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			jQuery("input[name='iloveimg_field_image']").val(attachment.url);
			jQuery('#iloveimg_settings__watermark__preview img').attr('src', attachment.url);

			// Update preview
			if (typeof changeTextStyle !== 'undefined') {
				changeTextStyle();
				resizeFont();
				changePosition();
			}
		});

		frame.open();
	});
};
