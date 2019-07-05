<?php 
//use iloveimg;
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://iloveimg.com/
 * @since             1.0.0
 * @package           iloveimgwatermark
 *
 * @wordpress-plugin
 * Plugin Name:       Best Watermark - Protect images on your site with iLoveIMG
 * Plugin URI:        https://developer.iloveimg.com/
 * Description:       Protect your site from image theft with our reliable and easy-to-use watermark plugin. Effective protection for your images.
 * Version:           1.0.0
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
 

include_once "admin/class-iloveimg-plugin.php";
include_once "admin/class-iloveimg-process.php";
include_once "admin/class-resources.php";
include_once "admin/class-serializer.php";
include_once "admin/class-submenu-page.php";
include_once "admin/class-submenu.php";
include_once "admin/class-table-media-bulk-optimized.php";
 
add_action( 'plugins_loaded', 'iLoveIMG_Watermark_custom_admin_settings' );



function iLoveIMG_Watermark_custom_admin_settings() {
    
    $serializer = new iLoveIMG_Watermark_Serializer();
    $serializer->init();
    
    $plugin = new iLoveIMG_Watermark_Submenu( new iLoveIMG_Watermark_Submenu_Page() );
    $plugin->init();
 
}

add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'iLoveIMG_Watermark_add_plugin_page_settings_link');
function iLoveIMG_Watermark_add_plugin_page_settings_link( $links ) {
	$links[] = '<a href="' .
		admin_url( 'admin.php?page=iloveimg-watermark-admin-page' ) .
		'">' . __('Settings') . '</a>';
    $links[] = '<a href="' .
        admin_url( 'upload.php?page=iloveimg-media-page' ) .
        '">' . __('Bulk Optimization') . '</a>';
	return $links;
}

function iLoveIMG_Watermark_activate(){
    add_option( 'iLoveIMG_Watermark_db_version', iLoveIMG_Watermark_COMPRESS_DB_VERSION );
    if(!get_option('iloveimg_options_watermark')){
        $iloveimg_thumbnails = ['full', 'thumbnail', 'medium', 'medium_large', 'large'];
        if(!extension_loaded('gd')){
            $iloveimg_thumbnails = ['full'];
        }
        update_option('iloveimg_options_watermark', 
            serialize(
                    [
                        //'iloveimg_field_watermark_activated' => 0,
                        //'iloveimg_field_autowatermark' => 1,
                        'iloveimg_field_type' => 'text',
                        'iloveimg_field_text' => 'Sample',
                        'iloveimg_field_scale' => 33,
                        'iloveimg_field_opacity' => 1,
                        'iloveimg_field_rotation' => 0,
                        'iloveimg_field_position' => 1,
                        'iloveimg_field_sizes' => $iloveimg_thumbnails,

                    ]
                )
            );
    }
}

register_activation_hook( __FILE__, 'iLoveIMG_Watermark_activate' );

new iLoveIMG_Watermark_Plugin();

$upload_path = wp_upload_dir();

define('iLoveIMG_Watermark_REGISTER_URL', 'https://api.iloveimg.com/v1/user');
define('iLoveIMG_Watermark_LOGIN_URL', 'https://api.iloveimg.com/v1/user/login');
define('iLoveIMG_Watermark_USER_URL', 'https://api.iloveimg.com/v1/user');
define('iLoveIMG_Watermark_NUM_MAX_FILES', 2);
define('iLoveIMG_Watermark_COMPRESS_DB_VERSION', '1.0');
define('iLoveIMG_upload_folder', $upload_path['basedir']);
define('iLoveIMG_Watermark_Plugin_URL', plugin_dir_url(__FILE__));
set_time_limit(300);