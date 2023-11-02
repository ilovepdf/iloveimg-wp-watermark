<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://iloveimg.com/
 * @since      1.0.0
 *
 * @package    iloveimgwatermark
 * @subpackage iloveimgwatermark/admin
 */
class Ilove_Img_Wm_Plugin {
    /**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   public
	 * @var      string    VERSION    The current version of the plugin.
	 */
    const VERSION = '1.0.3';

    /**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   public
	 * @var      string    NAME    The string used to uniquely identify this plugin.
	 */
	const NAME = 'ilove_img_watermark_plugin';

    /**
	 * This constructor defines the core functionality of the plugin.
     *
     * In this method, we set the plugin's name and version for reference throughout the codebase. We also load any necessary dependencies, define the plugin's locale for translation purposes, and set up hooks for the admin area.
     *
     * This constructor is executed when the plugin is initialized.
	 *
	 * @since    1.0.0
	 */
    public function __construct() {
        add_action( 'admin_init', array( $this, 'admin_init' ) );
    }

    /**
     * Initialize the admin functionalities and hooks for the ILoveImg Watermark plugin.
     *
     * This method sets up various admin-related functionalities and hooks for the ILoveImg Watermark plugin, including script enqueueing, column management, AJAX handlers, attachment processing, and more.
     *
     * @since    1.0.0
	 * @access   public
     */
    public function admin_init() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_filter( 'manage_media_columns', array( $this, 'column_id' ) );
        add_filter( 'manage_media_custom_column', array( $this, 'column_id_row' ), 10, 2 );
        add_action( 'wp_ajax_ilove_img_wm_library', array( $this, 'ilove_img_wm_library' ) );
        add_action( 'wp_ajax_ilove_img_wm_restore', array( $this, 'ilove_img_wm_restore' ) );
        add_action( 'wp_ajax_ilove_img_wm_clear_backup', array( $this, 'ilove_img_wm_clear_backup' ) );
        add_action( 'wp_ajax_ilove_img_wm_library_is_watermarked', array( $this, 'ilove_img_wm_library_is_watermarked' ) );
        add_action( 'wp_ajax_ilove_img_wm_library_set_watermark_image', array( $this, 'ilove_img_wm_library_set_watermark_image' ) );
        add_filter( 'wp_generate_attachment_metadata', array( $this, 'process_attachment' ), 10, 2 );
        add_action( 'admin_action_iloveimg_bulk_action', array( $this, 'media_library_bulk_action' ) );
        add_action( 'attachment_submitbox_misc_actions', array( $this, 'show_media_info' ) );

        if ( ! class_exists( 'ILove_Img_Wm_Library_Init' ) ) {
            require_once 'class-ilove-img-wm-library-init.php';
            new ILove_Img_Wm_Library_Init();
        }

        if ( ! self::check_iloveimg_plugins_is_activated() ) {
            add_action( 'admin_notices', array( $this, 'show_notices' ) );
        }

        add_thickbox();
    }

    /**
	 * Register scripts and styles for the admin area functionality of the plugin.
     *
     * This method is responsible for registering the necessary scripts and styles for the admin area functionality of the plugin.
	 *
	 * @since    1.0.0
	 * @access   public
	 */
    public function enqueue_scripts() {
        wp_enqueue_script(
            self::NAME . '_spectrum_admin',
            plugins_url( '/assets/js/spectrum.js', __DIR__ ),
            array(),
            self::VERSION,
            true
        );
        wp_enqueue_script(
            self::NAME . '_admin',
            plugins_url( '/assets/js/main.js', __DIR__ ),
			array(),
            self::VERSION,
            true
        );
        wp_enqueue_style(
            self::NAME . '_admin',
            plugins_url( '/assets/css/app.css', __DIR__ ),
			array(),
            self::VERSION
		);
    }

    /**
     * Handle the AJAX request to apply watermark to a media item.
     *
     * This method is responsible for processing an AJAX request to apply a watermark to a specific media item. It instantiates the `Ilove_Img_Wm_Process` class and uses it to watermark the attachment. The result is then rendered to display watermark details or an error message.
     */
    public function ilove_img_wm_library() {
        if ( isset( $_POST['id'] ) ) {
            $ilove         = new Ilove_Img_Wm_Process();
            $attachment_id = intval( $_POST['id'] );
            $images        = $ilove->watermark( $attachment_id );

            if ( $images !== false ) {
                Ilove_Img_Wm_Resources::render_watermark_details( $attachment_id );
            } else {
                ?>
                <p>You need more files</p>
                <?php
            }
        }

        wp_die();
    }

    /**
     * Handle the AJAX request to restore watermarked images.
     *
     * This method is responsible for processing an AJAX request to restore watermarked images. It checks for the presence of a backup folder, restores the original images from the backup, and removes associated metadata and options related to watermarked and compressed images.
     */
    public function ilove_img_wm_restore() {
        if ( is_dir( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' ) ) {
            $folders = array_diff( scandir( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' ), array( '..', '.' ) );

            foreach ( $folders as $key => $folder ) {
                Ilove_Img_Wm_Resources::rcopy( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup/' . $folder, ILOVE_IMG_WM_UPLOAD_FOLDER . '/' . $folder );
            }

            Ilove_Img_Wm_Resources::rrmdir( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' );

            $images_restore = unserialize( get_option( 'iloveimg_images_to_restore' ) );

            foreach ( $images_restore as $key => $value ) {
                delete_post_meta( $value, 'iloveimg_status_watermark' );
                delete_post_meta( $value, 'iloveimg_watermark' );
                delete_post_meta( $value, 'iloveimg_status_compress' );
                delete_post_meta( $value, 'iloveimg_compress' );
                delete_option( 'iloveimg_images_to_restore' );
            }
        }

        wp_die();
    }

    /**
     * Handle the AJAX request to clear the backup of watermarked images.
     *
     * This method is responsible for processing an AJAX request to clear the backup of watermarked images. It checks for the presence of a backup folder and, if found, deletes the entire backup folder and related options.
     */
    public function ilove_img_wm_clear_backup() {
        if ( is_dir( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' ) ) {
            Ilove_Img_Wm_Resources::rrmdir( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' );
            delete_option( 'iloveimg_images_to_restore' );
        }

        wp_die();
    }

    /**
     * Handle the AJAX request to check if a media item has been watermarked.
     *
     * This method processes an AJAX request to determine if a specific media item has been watermarked. It checks the watermarking status and, based on the status, sends an appropriate response or error code to the client.
     */
    public function ilove_img_wm_library_is_watermarked() {
        if ( isset( $_POST['id'] ) ) {
            $attachment_id     = intval( $_POST['id'] );
            $status_watermark  = get_post_meta( $attachment_id, 'iloveimg_status_watermark', true );
            $images_compressed = Ilove_Img_Wm_Resources::get_sizes_watermarked( $attachment_id );

            if ( ( (int) $status_watermark === 1 || (int) $status_watermark === 3 ) ) {
                http_respone_code( 500 );
            } elseif ( (int) $status_watermark === 2 ) {
                Ilove_Img_Wm_Resources::render_watermark_details( $attachment_id );
            } elseif ( (int) $status_watermark === 0 && ! $status_watermark ) {
                echo 'Try again or buy more files';
            }
        }

        wp_die();
    }

    /**
     * Add a custom column to the media library for displaying watermarking status.
     *
     * This method adds a custom column to the media library for displaying the watermarking status of media items. It checks if the watermarking feature is activated, and if so, it adds the 'Status Watermark' column to the list of columns.
     *
     * @param array $columns An array of column names and labels.
     * @return array Modified array of column names and labels.
     */
    public function column_id( $columns ) {
        if ( (int) Ilove_Img_Wm_Resources::is_activated() === 0 ) {
            return $columns;
        }

        $columns['iloveimg_status_watermark'] = __( 'Status Watermark', 'iloveimg-watermark' );

        return $columns;
    }

    /**
     * Display content in the custom 'Status Watermark' column of the media library.
     *
     * This method is responsible for displaying content in the custom 'Status Watermark' column of the media library. It checks if the specified column is 'iloveimg_status_watermark' and calls the `get_status_of_column` method to render the appropriate content for the column.
     *
     * @param string $column_name The name of the current column being processed.
     * @param int    $column_id The ID of the media item associated with the current column.
     */
    public function column_id_row( $column_name, $column_id ) {
        if ( $column_name == 'iloveimg_status_watermark' ) {
            Ilove_Img_Wm_Resources::get_status_of_column( $column_id );
        }
    }

    /**
     * Process attachments, update metadata, and potentially initiate watermarking.
     *
     * This method is responsible for processing attachments, updating metadata, and optionally initiating the watermarking process for a given attachment. It sets the initial watermarking status and decides whether to automatically watermark the attachment based on user settings and activation status.
     *
     * @param array $metadata Metadata for the attachment.
     * @param int   $attachment_id The ID of the attachment being processed.
     * @return array Modified metadata for the attachment.
     */
    public function process_attachment( $metadata, $attachment_id ) {
        update_post_meta( $attachment_id, 'iloveimg_status_watermark', 0 ); // status no watermarked

        if ( (int) Ilove_Img_Wm_Resources::is_auto_watermark() === 1 && Ilove_Img_Wm_Resources::is_loggued() && (int) Ilove_Img_Wm_Resources::is_activated() === 1 ) {
            wp_update_attachment_metadata( $attachment_id, $metadata );
            $this->async_watermark( $attachment_id );

        } elseif ( ! (int) Ilove_Img_Wm_Resources::is_auto_watermark() && (int) Ilove_Img_Wm_Resources::is_watermark_image() == 1 ) {
                $_wm_options                                 = unserialize( get_option( 'iloveimg_options_watermark' ) );
                $_wm_options['iloveimg_field_autowatermark'] = 1;

                update_option( 'iloveimg_options_watermark', serialize( $_wm_options ) );
                delete_option( 'iloveimg_options_is_watermark_image' );
        }

        return $metadata;
    }

    /**
     * Set the watermark image option in user settings.
     *
     * This method handles an AJAX request to set the watermark image option in user settings. It unsets the automatic watermarking option and updates the watermark image option in user settings.
     */
    public function ilove_img_wm_library_set_watermark_image() {
        $_wm_options = unserialize( get_option( 'iloveimg_options_watermark' ) );
        unset( $_wm_options['iloveimg_field_autowatermark'] );

        update_option( 'iloveimg_options_watermark', serialize( $_wm_options ) );
        update_option( 'iloveimg_options_is_watermark_image', 1 );

        wp_die();
    }

    /**
     * Initiate asynchronous watermarking for a given attachment.
     *
     * This method initiates the asynchronous watermarking process for a specified attachment by sending an AJAX request to the WordPress server. It sets up the request parameters, including the action and attachment ID, and sends the request asynchronously.
     *
     * @param int $attachment_id The ID of the attachment to be watermarked.
     */
    public function async_watermark( $attachment_id ) {
        $args = array(
            'method'    => 'POST',
            'timeout'   => 0.01,
            'blocking'  => false,
            'body'      => array(
				'action' => 'ilove_img_wm_library',
				'id'     => $attachment_id,
			),
            'cookies'   => isset( $_COOKIE ) && is_array( $_COOKIE ) ? $_COOKIE : array(),
            'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
        );

        if ( getenv( 'WORDPRESS_HOST' ) !== false ) {
			wp_remote_post( getenv( 'WORDPRESS_HOST' ) . '/wp-admin/admin-ajax.php', $args );
		} else {
			wp_remote_post( admin_url( 'admin-ajax.php' ), $args );
		}
    }

    /**
     * Perform a bulk action on selected media items in the library.
     *
     * This method handles a bulk action triggered in the media library. It processes selected media items based on the specified action. In this case, it initiates the watermarking process for each selected image if it hasn't been watermarked yet.
     * This method is intended to be used as a callback for the 'admin_action_iloveimg_bulk_action' action hook.
     */
    public function media_library_bulk_action() {
        die();

        foreach ( $_REQUEST['media'] as $attachment_id ) {
            $post = get_post( $attachment_id );

            if ( strpos( $post->post_mime_type, 'image/' ) !== false ) {
                $status_watermark = get_post_meta( $attachment_id, 'iloveimg_status_watermark', true );

                if ( (int) $status_watermark === 0 ) {
                    $this->async_watermark( $attachment_id );
                }
            }
        }
    }

    /**
     * Display notices to the user in the WordPress admin area.
     *
     * This method is responsible for displaying various notices to the user within the WordPress admin area. These notices provide important information or alerts related to the iLoveIMG plugin and user account status.
     * This method is typically used to show notices in the WordPress dashboard and is called within the admin_init() method.
     */
    public function show_notices() {
        if ( ! Ilove_Img_Wm_Resources::is_loggued() ) {
			?>
            <div class="notice notice-warning is-dismissible">
                <p><strong>iLoveIMG</strong> - Please you need to be logged or registered. <a href="<?php echo admin_url( 'admin.php?page=iloveimg-watermark-admin-page' ); ?>">Go to settings</a></p>
            </div>
            <?php
        }

        if ( get_option( 'iloveimg_account_error' ) ) {
                $iloveimg_account_error = unserialize( get_option( 'iloveimg_account_error' ) );
            if ( $iloveimg_account_error['action'] == 'login' ) :
                ?>
                <div class="notice notice-error is-dismissible">
                    <p>Your email or password is wrong.</p>
                </div>
            <?php endif; ?>
            <?php if ( $iloveimg_account_error['action'] == 'register' ) : ?>
                <div class="notice notice-error is-dismissible">
                    <p>This email address has already been taken.</p>
                </div>
            <?php endif; ?>
            <?php if ( $iloveimg_account_error['action'] == 'register_limit' ) : ?>
                <div class="notice notice-error is-dismissible">
                    <p>You have reached limit of different users to use this WordPress plugin. Please relogin with one of your existing users.</p>
                </div>
            <?php endif; ?>
            <?php

        }
        // do query
        if ( get_option( 'iloveimg_account' ) ) {
            $account = json_decode( get_option( 'iloveimg_account' ), true );
            if ( ! array_key_exists( 'error', $account ) ) {
                $token    = $account['token'];
                $response = wp_remote_get(
                    ILOVE_IMG_WM_USER_URL . '/' . $account['id'],
                    array(
                        'headers' => array( 'Authorization' => 'Bearer ' . $token ),
                    )
                );

                if ( isset( $response['response']['code'] ) && $response['response']['code'] == 200 ) {
                    $account = json_decode( $response['body'], true );
                    if ( $account['files_used'] >= $account['free_files_limit'] && $account['package_files_used'] >= $account['package_files_limit'] && @$account['subscription_files_used'] >= $account['subscription_files_limit'] ) {
                        ?>
                        <div class="notice notice-warning is-dismissible">
                            <p><strong>iLoveIMG</strong> - Please you need more files. <a href="https://developer.iloveimg.com/pricing" target="_blank">Buy more files</a></p>
                        </div>
                        <?php
                    }
                }
            }
        }
    }

    /**
     * Display additional iLoveIMG information in the WordPress media library.
     *
     * This method is responsible for adding a section to display iLoveIMG-related information for media attachments in the WordPress media library. It provides details about the watermarking status and a link to view watermark details.
     * This method is typically used to enhance the WordPress media library by adding iLoveIMG-specific details and is called within the `attachment_submitbox_misc_actions` hook.
     */
    public function show_media_info() {
        global $post;

        echo '<div class="misc-pub-section iloveimg-compress-images">';
        echo '<h4>';
        esc_html_e( 'iLoveIMG', 'iloveimg-watermark' );
        echo '</h4>';
        echo '<div class="iloveimg-container">';
        echo '<table><tr><td>';
        $status_watermark = get_post_meta( $post->ID, 'iloveimg_status_watermark', true );

        $images_compressed = Ilove_Img_Wm_Resources::get_sizes_watermarked( $post->ID );

        if ( (int) $status_watermark === 2 ) {
            Ilove_Img_Wm_Resources::render_watermark_details( $post->ID );
        } else {
            Ilove_Img_Wm_Resources::get_status_of_column( $post->ID );
        }

        echo '</td></tr></table>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Check if any 'iloveimg' related plugins are activated.
     *
     * This function iterates through all installed plugins and checks if any of them are related to 'iloveimg'. It specifically
     * looks for plugins with names that start with 'iloveimg'. If such a plugin is found, it further checks if it is active.
     * If an active 'iloveimg' plugin related to 'compress' is found, it returns true.
     *
     * @return bool True if an active 'iloveimg' related plugin is found, false otherwise.
     */
    public static function check_iloveimg_plugins_is_activated() {
        $all_plugins = get_plugins();

        $iloveimg_compress_found = false;

        foreach ( $all_plugins as $plugin_file => $plugin_info ) {

            if ( strpos( $plugin_file, 'iloveimg' ) === 0 ) {

                if ( is_plugin_active( $plugin_file ) ) {

                    if ( strpos( $plugin_file, 'compress' ) !== false ) {
                        $iloveimg_compress_found = true;

                        return $iloveimg_compress_found;
                    }
                }
            }
        }

        return $iloveimg_compress_found;
    }
}