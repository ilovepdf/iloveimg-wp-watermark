<?php

use Ilove_Img_Wm\Ilove_Img_Wm_Resources;
?>
<h2 class="ilovepdf-base__layout-flex ilovepdf-base__layout-gap--small ilovepdf-base__layout-items-center"><?php echo esc_html_x( 'Summary', 'title: admin settings overview', 'iloveimg-watermark' ); ?></h2>
<article class="iloveimg_settings__overview__statistics">
		<h3 class="ilovepdf-base__layout-flex ilovepdf-base__layout-gap--small ilovepdf-base__layout-items-center">
            <svg width="20px" height="20px" viewBox="0 0 20 20" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"> <g id="Plugin-WP-iLoveIMG" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <g id="Home-Register" transform="translate(-48.000000, -667.000000)"> <g id="watermark_20x20" transform="translate(48.000000, 667.000000)"> <path d="M3.2048565,0 L16.7951435,0 C17.9095419,0 18.3136497,0.116032014 18.7210571,0.33391588 C19.1284645,0.551799746 19.4482003,0.871535463 19.6660841,1.27894287 C19.883968,1.68635028 20,2.09045808 20,3.2048565 L20,16.7951435 C20,17.9095419 19.883968,18.3136497 19.6660841,18.7210571 C19.4482003,19.1284645 19.1284645,19.4482003 18.7210571,19.6660841 C18.3136497,19.883968 17.9095419,20 16.7951435,20 L3.2048565,20 C2.09045808,20 1.68635028,19.883968 1.27894287,19.6660841 C0.871535463,19.4482003 0.551799746,19.1284645 0.33391588,18.7210571 C0.116032014,18.3136497 0,17.9095419 0,16.7951435 L0,3.2048565 C0,2.09045808 0.116032014,1.68635028 0.33391588,1.27894287 C0.551799746,0.871535463 0.871535463,0.551799746 1.27894287,0.33391588 C1.68635028,0.116032014 2.09045808,0 3.2048565,0 Z" id="Rectangle-6-Copy-49" fill="#AB6993"></path> <path d="M9.08222222,9.00978182 C9.08222222,9.3536 8.64472222,10.2179364 8.23861111,10.8757 C8.20083333,10.9371364 8.18777778,11.0154818 8.20722222,11.0853727 C8.23805556,11.1961273 8.33777778,11.2730636 8.45138889,11.2730636 L11.5491667,11.2730636 C11.645,11.2730636 11.7327778,11.2181091 11.7755556,11.1310273 C11.8186111,11.0442273 11.81,10.9399545 11.7527778,10.8618909 C11.1752778,10.0736455 10.9177778,9.5024 10.9177778,9.0095 C10.9177778,8.51688182 11.1752778,7.94563636 11.755,7.15344545 C12.02,6.77890909 12.1602778,6.3373 12.1602778,5.87596364 C12.1602778,4.66780909 11.1913889,3.68454545 10,3.68454545 C8.80861111,3.68454545 7.83972222,4.66752727 7.83972222,5.87596364 C7.83972222,6.33701818 7.97972222,6.77890909 8.2475,7.15710909 C8.82472222,7.94563636 9.08222222,8.51688182 9.08222222,9.00978182 Z" id="Shape" fill="#FFFFFF" fill-rule="nonzero"></path> <path d="M14.6888889,11.6321 L5.31111111,11.6321 C5.17111111,11.6321 5.0575,11.7473636 5.0575,11.8894 L5.0575,14.4756455 C5.0575,14.6176818 5.17083333,14.7329455 5.31111111,14.7329455 L14.6888889,14.7329455 C14.8288889,14.7329455 14.9425,14.6176818 14.9425,14.4756455 L14.9425,11.8894 C14.9425,11.7473636 14.8288889,11.6321 14.6888889,11.6321 Z" id="Shape" fill="#FFFFFF" fill-rule="nonzero"></path> <path d="M13.2544444,15.1094545 L6.74611111,15.1094545 C6.60611111,15.1094545 6.4925,15.2247182 6.4925,15.3667545 L6.4925,15.6584364 C6.4925,15.8004727 6.60583333,15.9157364 6.74611111,15.9157364 L13.2544444,15.9157364 C13.3944444,15.9157364 13.5080556,15.8004727 13.5080556,15.6584364 L13.5080556,15.3667545 C13.5077778,15.2247182 13.3944444,15.1094545 13.2544444,15.1094545 Z" id="Shape" fill="#FFFFFF" fill-rule="nonzero"></path> </g> </g> </g></svg>
			<?php echo esc_html_x( 'Watermark image', 'Overview: tool title', 'iloveimg-watermark' ); ?>
	</h3>
	<div style="margin-top: 20px;">
		<div class="iloveimg_settings__overview__statistics__column_left">
			<div class="iloveimg_percent  ">
				<div class="iloveimg_percent-total" style="width: <?php echo ( Ilove_Img_Wm_Resources::get_total_images() > 0 ) ? (float) round( ( Ilove_Img_Wm_Resources::get_watermarked_files() * 100 ) / Ilove_Img_Wm_Resources::get_total_images() ) : 0; ?>%;"></div>
			</div>
			<div class="iloveimg_saving">
				<?php
				$ilove_img_wm_porcentage_protected = Ilove_Img_Wm_Resources::get_total_images() > 0 ? (float) round( ( Ilove_Img_Wm_Resources::get_watermarked_files() * 100 ) / Ilove_Img_Wm_Resources::get_total_images() ) : 0;

				printf(
					wp_kses_post(
						/* translators: %s porcentage of images protected */
						__( '%s Images watermarked', 'iloveimg-watermark' )
					),
					'<p class="iloveimg_saving__number">' . (float) $ilove_img_wm_porcentage_protected . '%</p>'
				);
				?>
			</div>
		</div>
		<div class="iloveimg_settings__overview__statistics__column_right">
			<div class="iloveimg_saving">
				<?php
				printf(
					wp_kses_post(
						/* translators: %s backup size */
						__( '%s Backup storage used', 'iloveimg-watermark' )
					),
					'<p class="iloveimg_saving__number">' . (float) round( Ilove_Img_Wm_Resources::get_size_backup(), 2 ) . ' MB</p>'
				);
				?>
			</div>
		</div>
	</div>
	<h4>
		<?php
		$ilove_img_wm_line_scaped_summary = esc_html_x( "Your images, summary:\nTotal images: %2\$s\nWatermarked images: %1\$s", 'Overview: watermark summary', 'iloveimg-watermark' );
		$ilove_img_wm_formatted_summary   = nl2br( $ilove_img_wm_line_scaped_summary );
		$ilove_img_wm_allowed_tags        = array(
			'br'   => array(),
			'br/'  => array(),
			'br /' => array(),
		);
		$ilove_img_wm_output_html         = wp_kses( $ilove_img_wm_formatted_summary, $ilove_img_wm_allowed_tags );
		printf(
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped via wp_kses above
			$ilove_img_wm_output_html,
			/* translators: %s number of watermarked files and total images */
			'<strong>' . (int) Ilove_Img_Wm_Resources::get_watermarked_files() . '</strong>',
			'<strong>' . (int) Ilove_Img_Wm_Resources::get_total_images() . '</strong>'
		);
		?>
	</h4>
</article>
