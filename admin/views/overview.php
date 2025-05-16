<?php
use Ilove_Img_Wm\Ilove_Img_Wm_Resources;
?>
<div class="iloveimg_settings__overview__statistics">
	<h3><?php esc_html_e( 'Overview', 'iloveimg-watermark' ); ?></h3>
	<div>
		<div class="iloveimg_settings__overview__statistics__column_left">
			<h4><?php esc_html_e( 'Watermarked images', 'iloveimg-watermark' ); ?> <strong><?php echo (int) Ilove_Img_Wm_Resources::get_watermarked_files(); ?></strong> / <?php esc_html_e( 'Uploaded images', 'iloveimg-watermark' ); ?> <strong><?php echo (int) Ilove_Img_Wm_Resources::get_total_images(); ?></strong></h4>
			<div class="iloveimg_percent  ">
                <div class="iloveimg_percent-total" style="width: <?php echo ( Ilove_Img_Wm_Resources::get_total_images() > 0 ) ? (float) round( ( Ilove_Img_Wm_Resources::get_watermarked_files() * 100 ) / Ilove_Img_Wm_Resources::get_total_images() ) : 0; ?>%;"></div>
            </div>
            <div class="iloveimg_saving">
            	<p class="iloveimg_saving__number"><?php echo ( Ilove_Img_Wm_Resources::get_total_images() > 0 ) ? (float) round( ( Ilove_Img_Wm_Resources::get_watermarked_files() * 100 ) / Ilove_Img_Wm_Resources::get_total_images() ) : 0; ?>%</p>
            	<p><?php esc_html_e( 'Total images protected with iLoveIMG', 'iloveimg-watermark' ); ?></p>
            </div>
		</div>
		<div class="iloveimg_settings__overview__statistics__column_right">
			
            <div class="iloveimg_saving">
            	<p class="iloveimg_saving__number"><?php echo (float) round( Ilove_Img_Wm_Resources::get_size_backup(), 2 ); ?> MB</p>
            	<p><?php esc_html_e( 'From backup images', 'iloveimg-watermark' ); ?></p>
            </div>
		</div>
	</div>
</div>