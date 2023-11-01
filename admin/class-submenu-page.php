<?php
/**
 * Creates the submenu page for the plugin.
 *
 * @package Custom_Admin_Settings
 */

/**
 * Creates the submenu page for the plugin.
 *
 * Provides the functionality necessary for rendering the page corresponding
 * to the submenu with which this page is associated.
 *
 * @package Custom_Admin_Settings
 */
class Ilove_Img_Wm_Submenu_Page {

    public function renderParent() {
    }

	public function renderCompress() {
		if ( ! is_plugin_active( 'iloveimg/iloveimgcompress.php' ) ) {
        	require_once 'views/compress.php';
    	}
	}

	public function renderWatermark() {
		wp_enqueue_media();
        $options_value = unserialize( get_option( 'iloveimg_options_watermark' ) );
        require_once 'views/watermark.php';
	}

	public function renderMediaOptimization() {
		$options_value = unserialize( get_option( 'iloveimg_options_compress' ) );
		require_once 'views/media_bulk.php';
	}
}
