<?php
/**
 * Creates the submenu page for the plugin.
 *
 * @package iloveimgwatermark
 */

/**
 * Creates the submenu page for the plugin.
 *
 * Provides the functionality necessary for rendering the page corresponding
 * to the submenu with which this page is associated.
 *
 * @package iloveimgwatermark
 */
class Ilove_Img_Wm_Submenu_Page {

	/**
     * Render submenu parent.
     */
    public function render_parent() {
    }

	/**
	 * Render the compress functionality in the WordPress plugin.
	 *
	 * This method is responsible for rendering the compress functionality within the WordPress plugin. It includes a conditional check to determine whether the "iloveimg" plugin is active. If the "iloveimg" plugin is not active, it requires the "views/compress.php" file, which likely contains the UI for the compress functionality.
     *
	 * The method is typically used to display the compress-related view or user interface elements for the plugin, allowing users to interact with the compress feature. It ensures that the compress functionality is available when the required dependencies are met.
	 */
	public function render_compress() {
		if ( ! Ilove_Img_Wm_Plugin::check_iloveimg_plugins_is_activated() ) {
        	require_once 'views/compress.php';
    	}
	}

	/**
	 * Render the watermark functionality in the plugin.
	 *
	 * This method is responsible for rendering the watermark functionality within the plugin. It enqueues the necessary media scripts and styles to work with media-related features. It retrieves the watermark options from the 'iloveimg_options_watermark' option and requires the "views/watermark.php" file to display the watermark-related user interface.
	 *
	 * The "views/watermark.php" file is expected to contain the HTML and UI components for configuring and applying watermarks to media files, such as images. This method ensures that the watermark functionality is accessible to users.
	 */
	public function render_watermark() {
		wp_enqueue_media();
        $options_value = json_decode( get_option( 'iloveimg_options_watermark' ), true );
        require_once 'views/watermark.php';
	}

	/**
	 * Render the media optimization functionality in the plugin.
	 *
	 * This method is responsible for rendering the media optimization functionality within the plugin. It retrieves the media optimization options from the 'iloveimg_options_compress' option and requires the "views/media-bulk.php" file to display the user interface for bulk media optimization.
	 *
	 * The "views/media-bulk.php" file is expected to contain the HTML, form elements, and UI components for configuring and performing bulk media optimization operations. This method ensures that the media optimization functionality is accessible to users.
	 */
	public function render_media_optimization() {
		$options_value = json_decode( get_option( 'iloveimg_options_compress' ), true );
		require_once 'views/media-bulk.php';
	}
}
