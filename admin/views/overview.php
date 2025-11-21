<?php

use Ilove_Img_Wm\Ilove_Img_Wm_Resources;
?>
<h2 class="ilovepdf-base__layout-flex ilovepdf-base__layout-gap--small ilovepdf-base__layout-items-center"><?php echo esc_html_x( 'Overview', 'title: admin settings overview', 'iloveimg-watermark' ); ?></h2>
<article class="iloveimg_settings__overview__statistics">
	<div>
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
						__( '%s Total images protected with iLoveIMG', 'iloveimg-watermark' )
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
						__( '%s From backup images', 'iloveimg-watermark' )
					),
					'<p class="iloveimg_saving__number">' . (float) round( Ilove_Img_Wm_Resources::get_size_backup(), 2 ) . ' MB</p>'
				);
				?>
			</div>
		</div>
	</div>
	<h4>
		<?php
		$ilove_img_wm_line_scaped_summary = esc_html_x( "Your images, summary:\nWatermarked images %1\$s\nUploaded images %2\$s", 'Overview: watermark summary', 'iloveimg-watermark' );
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