<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://iloveimg.com/
 * @since             1.0.1
 * @package           iloveimgwatermark
 *
 * @wordpress-plugin
 * Plugin Name:       Best Watermark - Protect images on your site with iLoveIMG
 * Plugin URI:        https://iloveapi.com/
 * Description:       Protect your site from image theft with our reliable and easy-to-use watermark plugin. Effective protection for your images.
 * Version:           2.2.0
 * Requires at least: 5.3
 * Requires PHP:      7.4
 * Author:            iLoveIMG
 * Author URI:        https://iloveimg.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       iloveimg-watermark
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

require_once plugin_dir_path( __FILE__ ) . '/vendor/autoload.php';

if ( ini_get( 'max_execution_time' ) < 300 ) {
    set_time_limit( 300 );
}

$ilove_img_wm_upload_path = wp_upload_dir();

define( 'ILOVE_IMG_WM_REGISTER_URL', 'https://api.ilovepdf.com/v1/user' );
define( 'ILOVE_IMG_WM_LOGIN_URL', 'https://api.ilovepdf.com/v1/user/login' );
define( 'ILOVE_IMG_WM_USER_URL', 'https://api.ilovepdf.com/v1/user' );
define( 'ILOVE_IMG_WM_NUM_MAX_FILES', 2 );
define( 'ILOVE_IMG_WM_COMPRESS_DB_VERSION', '1.0' );
define( 'ILOVE_IMG_WM_UPLOAD_FOLDER', $ilove_img_wm_upload_path['basedir'] );
define( 'ILOVE_IMG_WM_BACKUP_FOLDER', $ilove_img_wm_upload_path['basedir'] . '/iloveimg-backup/' );
define( 'ILOVE_IMG_WM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

use Ilove_Img_Wm\Ilove_Img_Wm_Plugin;
use Ilove_Img_Wm\Ilove_Img_Wm_Serializer;
use Ilove_Img_Wm\Ilove_Img_Wm_Submenu;
use Ilove_Img_Wm\Ilove_Img_Wm_Submenu_Page;

/**
 * Initialize admin settings
 *
 * This function runs when plugins are loaded in WordPress.
 */
function ilove_img_wm_custom_admin_settings() {

    $serializer = new Ilove_Img_Wm_Serializer();
    $serializer->init();

    $plugin = new Ilove_Img_Wm_Submenu( new Ilove_Img_Wm_Submenu_Page() );
    $plugin->init();
}
add_action( 'plugins_loaded', 'ilove_img_wm_custom_admin_settings' );

/**
 * Add links on WordPress admin
 *
 * This function adds links to the plugin's settings and bulk optimization pages in the WordPress admin dashboard.
 *
 * @param array $links An array of existing plugin action links.
 * @return array An array with the added plugin action links.
 */
function ilove_img_wm_add_plugin_page_settings_link( $links ) {

	$links[] = '<a href="' .
		admin_url( 'admin.php?page=iloveimg-watermark-admin-page' ) .
		'">' . __( 'Settings', 'iloveimg-watermark' ) . '</a>';

    $links[] = '<a href="' .
        admin_url( 'upload.php?page=iloveimg-media-page' ) .
        '">' . __( 'Bulk Optimization', 'iloveimg-watermark' ) . '</a>';

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ilove_img_wm_add_plugin_page_settings_link' );

/**
 * Add Options to watermark page admin settings
 *
 * This function is executed when the plugin is activated. It performs the following tasks:
 * 1. Adds an option to store the plugin's database version.
 * 2. Sets default options for watermarking if they don't already exist in the WordPress options.
 */
function ilove_img_wm_activate() {
    add_option( 'ilove_img_wm_db_version', ILOVE_IMG_WM_COMPRESS_DB_VERSION );

    if ( ! file_exists( ILOVE_IMG_WM_BACKUP_FOLDER ) ) {
        wp_mkdir_p( ILOVE_IMG_WM_BACKUP_FOLDER );
    }

    if ( ! get_option( 'iloveimg_options_watermark' ) ) {

        $iloveimg_thumbnails = array( 'full', 'thumbnail', 'medium', 'medium_large', 'large' );

        if ( ! extension_loaded( 'gd' ) ) {
            $iloveimg_thumbnails = array( 'full' );
        }

        update_option(
            'iloveimg_options_watermark',
            wp_json_encode(
                array(
					'iloveimg_field_type'     => 'text',
					'iloveimg_field_text'     => 'Sample',
					'iloveimg_field_scale'    => 33,
					'iloveimg_field_opacity'  => 1,
					'iloveimg_field_rotation' => 0,
					'iloveimg_field_position' => 1,
					'iloveimg_field_sizes'    => $iloveimg_thumbnails,
                    'iloveimg_field_backup'   => 'on',
				)
            )
        );
    } else {
        $old_data = get_option( 'iloveimg_options_watermark' );

        if ( is_serialized( $old_data ) ) {
            $old_data_serialize = unserialize( get_option( 'iloveimg_options_watermark' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
            update_option( 'iloveimg_options_watermark', wp_json_encode( $old_data_serialize ) );
        }
    }
}
register_activation_hook( __FILE__, 'ilove_img_wm_activate' );

new Ilove_Img_Wm_Plugin();
