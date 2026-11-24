<?php
namespace Ilove_Img_Wm;

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
		add_action( 'load-media_page_iloveimg-media-watermark-page', array( $this, 'handle_bulk_watermark_action' ) );
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
				_x( 'Compress settings', 'submenu', 'iloveimg-watermark' ),
				_x( 'Compress settings', 'submenu', 'iloveimg-watermark' ),
				'manage_options',
				'iloveimg-compress-admin-page',
				array(
					$this->submenu_page,
					'render_compress',
				)
			);

			add_submenu_page(
				'iloveimg-admin-page',
				_x( 'Watermark settings', 'submenu', 'iloveimg-watermark' ),
				_x( 'Watermark settings', 'submenu', 'iloveimg-watermark' ),
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
				_x( 'Watermark settings', 'submenu', 'iloveimg-watermark' ),
				_x( 'Watermark settings', 'submenu', 'iloveimg-watermark' ),
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
			_x( 'Bulk Watermark', 'submenu', 'iloveimg-watermark' ),
			'upload_files',
			'iloveimg-media-watermark-page',
			array(
				$this->submenu_page,
				'render_media_optimization',
			)
		);
	}

	/**
	 * No-JS safety net for the bulk watermark form submission.
	 *
	 * When JavaScript is present the form submit is preventDefault()-ed by the
	 * bulk queue in bulk/index.js, so this handler never runs in the happy path.
	 * It exists solely as a graceful fallback: if a no-JS GET of action
	 * `iloveimg_watermark` reaches the server, verify the nonce and register a
	 * single "JavaScript required" admin notice instead of silently reloading.
	 *
	 * Capability enforcement: this load hook is only registered for
	 * `media_page_iloveimg-media-watermark-page`, whose add_media_page() cap is
	 * already `upload_files`.
	 *
	 * @since 1.0.0
	 */
	public function handle_bulk_watermark_action() {
		// Verify nonce using the field name and action that the template actually sends.
		$nonce = isset( $_REQUEST['iloveimg_wm_bulk_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['iloveimg_wm_bulk_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'iloveimg_wm_bulk_watermark_action' ) ) {
			return;
		}

		// Defense-in-depth: re-verify capability after nonce check (parity with compress handler).
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		// Check if this is a form submission with the watermark bulk action.
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above
		if ( empty( $action ) || '-1' === $action ) {
			$action = isset( $_REQUEST['action2'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action2'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above
		}

		// Only handle our specific bulk action.
		if ( 'iloveimg_watermark' !== $action ) {
			return;
		}

		// A no-JS GET reached the server: register a "JavaScript required" info notice.
		// JS-enabled browsers never reach this point (the submit is intercepted client-side).
		add_action( 'admin_notices', array( $this, 'render_js_required_notice' ) );
	}

	/**
	 * Render an admin notice informing the user that JavaScript is required.
	 *
	 * Hooked onto `admin_notices` only when a no-JS bulk-watermark form GET is detected
	 * by handle_bulk_watermark_action().
	 *
	 * @since 1.0.0
	 */
	public function render_js_required_notice() {
		echo '<div class="notice notice-info"><p>' .
			esc_html__( 'JavaScript is required for bulk watermarking. Please enable JavaScript and try again.', 'iloveimg-watermark' ) .
			'</p></div>';
	}
}
