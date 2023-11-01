<div class="iloveimg_settings__overview__statistics">
	<h3>Overview</h3>
	<div>
		<div class="iloveimg_settings__overview__statistics__column_right">
			<h4>Watermarked images <strong><?php echo iLoveIMG_Watermark_Resources::getFilesCompressed(); ?></strong> / Uploaded images <strong><?php echo iLoveIMG_Watermark_Resources::getTotalImages(); ?></strong></h4>
			<div class="iloveimg_percent  ">
                <div class="iloveimg_percent-total" style="width: <?php echo ( iLoveIMG_Watermark_Resources::getTotalImages() > 0 ) ? round( ( iLoveIMG_Watermark_Resources::getFilesCompressed() * 100 ) / iLoveIMG_Watermark_Resources::getTotalImages() ) : 0; ?>%;"></div>
            </div>
            <div class="iloveimg_saving">
            	<p class="iloveimg_saving__number"><?php echo ( iLoveIMG_Watermark_Resources::getTotalImages() > 0 ) ? round( ( iLoveIMG_Watermark_Resources::getFilesCompressed() * 100 ) / iLoveIMG_Watermark_Resources::getTotalImages() ) : 0; ?>%</p>
            	<p>Total images protected with iLoveIMG</p>
            </div>
		</div>
		<div class="iloveimg_settings__overview__statistics__column_right">
			
            <div class="iloveimg_saving">
            	<p class="iloveimg_saving__number"><?php echo round( iLoveIMG_Watermark_Resources::getSizeBackup(), 2 ); ?> MB</p>
            	<p>From backup  images</p>
            </div>
		</div>
	</div>
</div>
<!-- <div class="iloveimg_settings__overview__compress-all">
	<button type="button" id="iloveimg_allcompress" class="iloveimg-compress-all button button-small button-primary">Compress All</button>
</div> -->