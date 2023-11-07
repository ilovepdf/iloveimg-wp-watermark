<?php
/**
 * Class for managing the iLoveIMG plugin's submenu and pages.
 *
 * This class is responsible for adding a submenu to the 'Tools' menu in the WordPress admin area and rendering the plugin's settings and content pages. It initializes the submenu and adds individual pages for watermark settings, and media optimization.
 *
 * @since 1.0.0
 */
class Ilove_Img_Wm_Submenu {

	/**
	 * A reference the class responsible for rendering the submenu page.
	 *
	 * @var    Ilove_Img_Wm_Submenu_Page
	 * @access private
	 */
	private $submenu_page;

	/**
	 * Initializes all of the partial classes.
	 *
	 * @param Ilove_Img_Wm_Submenu_Page $submenu_page A reference to the class that renders the
	 * page for the plugin.
	 */
	public function __construct( $submenu_page ) {
		$this->submenu_page = $submenu_page;
	}

	/**
	 * Adds a submenu for this plugin to the 'Tools' menu.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_options_page' ), 999 );
	}

	/**
	 * Creates the submenu item and calls on the Submenu Page object to render
	 * the actual contents of the page.
	 */
	public function add_options_page() {
		if ( ! Ilove_Img_Wm_Plugin::check_iloveimg_plugins_is_activated() ) {

			add_menu_page(
				'iLoveIMG',
				'iLoveIMG',
				'manage_options',
				'iloveimg-admin-page',
				array( $this->submenu_page, 'render_parent' ),
				'https://www.iloveimg.com/img/favicons-img/favicon-16x16.png'
			);

			add_submenu_page(
				'iloveimg-admin-page',
				'Compress settings',
				'Compress settings',
				'manage_options',
				'iloveimg-compress-admin-page',
				array(
					$this->submenu_page,
					'render_compress',
				)
			);

			add_submenu_page(
				'iloveimg-admin-page',
				'Watermark settings',
				'Watermark settings',
				'manage_options',
				'iloveimg-watermark-admin-page',
				array(
					$this->submenu_page,
					'render_watermark',
				)
			);

		} else {
			remove_submenu_page( 'iloveimg-admin-page', 'iloveimg-watermark-admin-page' );
			add_submenu_page(
				'iloveimg-admin-page',
				'Watermark settings',
				'Watermark settings',
				'manage_options',
				'iloveimg-watermark-admin-page',
				array(
					$this->submenu_page,
					'render_watermark',
				)
			);
		}
		remove_submenu_page( 'iloveimg-admin-page', 'iloveimg-admin-page' );

		add_media_page(
			'iLoveIMG Media',
			'Bulk Watermark',
			'manage_options',
			'iloveimg-media-watermark-page',
			array(
				$this->submenu_page,
				'render_media_optimization',
			)
		);
	}

	/**
	 * Render the settings page content for the WordPress plugin.
	 *
	 * This method is responsible for displaying the content of the settings page for the WordPress plugin. In a real plugin, you would use this method to render and display the configuration options and settings that users can modify.
	 *
	 * The provided example simply displays a text message, "This is the page content," which should be replaced with the actual settings and user interface elements for your plugin.
	 */
	public function settings_page() {
		echo 'This is the page content';
	}
}
