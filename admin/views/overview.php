<?php
use Ilove_Img_Wm\Ilove_Img_Wm_Resources;
?>
<div class="iloveimg_settings__overview__statistics">
	<h3><?php echo esc_html_x( 'Overview', 'title: admin settings overview', 'iloveimg-watermark' ); ?></h3>
	<div>
		<div class="iloveimg_settings__overview__statistics__column_left">
			<h4>
				<?php
				printf(
					wp_kses_post(
						/* translators: %1$s and %2$s number images */
						__( 'Watermarked images %1$s / Uploaded images %2$s', 'iloveimg-watermark' )
					),
					'<strong>' . (int) Ilove_Img_Wm_Resources::get_watermarked_files() . '</strong>',
					'<strong>' . (int) Ilove_Img_Wm_Resources::get_total_images() . '</strong>'
				);
				?>
			</h4>
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
</div>