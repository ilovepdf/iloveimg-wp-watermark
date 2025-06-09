<?php
namespace Ilove_Img_Wm;

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
    const VERSION = '2.2.11';

    /**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   public
	 * @var      string    NAME    The string used to uniquely identify this plugin.
	 */
	const NAME = 'Ilove_Img_Wm_plugin';

    /**
	 * The unique nonce identifier.
	 *
	 * @since    1.0.4
	 * @access   public
	 * @var      string    $img_nonce    The string used to uniquely nonce identify.
	 */
	protected static $img_nonce;

    /**
	 * File formats.
	 *
	 * @since    2.2.10
	 * @access   public
	 * @var      array    $accepted_file_format    List of accepted file formats.
	 */
	public static $accepted_file_format = array( 'image/jpeg', 'image/jpg', 'image/png', 'image/gif' );

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
        // create nonce
        self::$img_nonce = wp_create_nonce();

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_filter( 'manage_media_columns', array( $this, 'column_id' ) );
        add_filter( 'manage_media_custom_column', array( $this, 'column_id_row' ), 10, 2 );
        add_action( 'wp_ajax_ilove_img_wm_library', array( $this, 'ilove_img_wm_library' ) );
        add_action( 'wp_ajax_ilove_img_wm_restore_all', array( $this, 'ilove_img_wm_restore_all' ) );
        add_action( 'wp_ajax_ilove_img_wm_restore', array( $this, 'ilove_img_restore' ) );
        add_action( 'wp_ajax_ilove_img_wm_clear_backup', array( $this, 'ilove_img_wm_clear_backup' ) );
        add_action( 'wp_ajax_ilove_img_wm_library_is_watermarked', array( $this, 'ilove_img_wm_library_is_watermarked' ) );
        add_action( 'wp_ajax_ilove_img_wm_library_set_watermark_image', array( $this, 'ilove_img_wm_library_set_watermark_image' ) );
        add_filter( 'wp_generate_attachment_metadata', array( $this, 'process_attachment' ), 10, 2 );
        add_filter( 'bulk_actions-upload', array( $this, 'add_bulk_watermark_action' ) );
        add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_watermark_action' ), 10, 3 );
        add_filter( 'query_vars', array( $this, 'add_iloveimgwm_custom_query_vars' ) );
        add_action( 'attachment_submitbox_misc_actions', array( $this, 'show_media_info' ) );

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

        global $pagenow, $hook_suffix;

		if ( ( 'upload.php' === $pagenow || 'iloveimg_page_iloveimg-watermark-admin-page' === $hook_suffix || 'iloveimg_page_iloveimg-compress-admin-page' === $hook_suffix || 'media-new.php' === $pagenow || 'post.php' === $pagenow ) && get_current_screen()->post_type !== 'product' ) {

            wp_enqueue_script(
                self::NAME . '_spectrum_admin',
                plugins_url( '/assets/js/spectrum.min.js', __DIR__ ),
                array(),
                '1.8.0',
                true
            );
            wp_enqueue_script(
                self::NAME . '_admin',
                plugins_url( '/assets/js/main.min.js', __DIR__ ),
                array( self::NAME . '_spectrum_admin' ),
                self::VERSION,
                true
            );
            wp_enqueue_style(
                self::NAME . '_admin',
                plugins_url( '/assets/css/app.min.css', __DIR__ ),
                array(),
                self::VERSION
            );
        }
    }

    /**
     * Handle the AJAX request to apply watermark to a media item.
     *
     * This method is responsible for processing an AJAX request to apply a watermark to a specific media item. It instantiates the `Ilove_Img_Wm_Process` class and uses it to watermark the attachment. The result is then rendered to display watermark details or an error message.
     */
    public function ilove_img_wm_library() {
        if ( isset( $_POST['id'] ) && isset( $_POST['imgnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['imgnonce'] ) ) ) ) {
            $ilove         = new Ilove_Img_Wm_Process();
            $attachment_id = intval( $_POST['id'] );
            $images        = $ilove->watermark( $attachment_id );

            if ( ! $images['error'] ) {
                Ilove_Img_Wm_Resources::render_watermark_details( $attachment_id );
            } else {
                ?>
                <p><?php echo esc_html( $images['error_msg'] ); ?></p>
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
    public function ilove_img_wm_restore_all() {

        if ( is_dir( ILOVE_IMG_WM_BACKUP_FOLDER ) ) {
            $images_restore = json_decode( get_option( 'iloveimg_images_to_restore' ), true );

            foreach ( $images_restore as $key => $value ) {
                Ilove_Img_Wm_Resources::rcopy( ILOVE_IMG_WM_BACKUP_FOLDER . basename( get_attached_file( $value ) ), get_attached_file( $value ) );
                Ilove_Img_Wm_Resources::regenerate_attachment_data( $value );

                delete_post_meta( $value, 'iloveimg_status_watermark' );
                delete_post_meta( $value, 'iloveimg_watermark' );
                delete_post_meta( $value, 'iloveimg_status_compress' );
                delete_post_meta( $value, 'iloveimg_compress' );
                wp_delete_file( ILOVE_IMG_WM_BACKUP_FOLDER . basename( get_attached_file( $value ) ) );
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
        if ( is_dir( ILOVE_IMG_WM_BACKUP_FOLDER ) ) {
            Ilove_Img_Wm_Resources::rrmdir( ILOVE_IMG_WM_BACKUP_FOLDER );
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
        if ( isset( $_POST['id'] ) && isset( $_POST['imgnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['imgnonce'] ) ) ) ) {
            $attachment_id    = intval( $_POST['id'] );
            $status_watermark = get_post_meta( $attachment_id, 'iloveimg_status_watermark', true );
            Ilove_Img_Wm_Resources::get_sizes_watermarked( $attachment_id );

            if ( ( 1 === (int) $status_watermark || 3 === (int) $status_watermark ) ) {
                http_response_code( 500 );
            } elseif ( 2 === (int) $status_watermark ) {
                Ilove_Img_Wm_Resources::render_watermark_details( $attachment_id );
            } elseif ( 0 === (int) $status_watermark && ! $status_watermark ) {
                esc_html_e( 'Try again or buy more credits', 'iloveimg-watermark' );
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

        $columns['iloveimg_status_watermark'] = _x( 'Status Watermark', 'column name', 'iloveimg-watermark' );

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
        if ( 'iloveimg_status_watermark' === $column_name ) {
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
        $file = get_post( $attachment_id );

        if ( ! in_array( $file->post_mime_type, self::$accepted_file_format, true ) ) {
            return $metadata;
        }

        update_post_meta( $attachment_id, 'iloveimg_status_watermark', 0 ); // status no watermarked

        $images_restore = null !== get_option( 'iloveimg_images_to_restore', null ) ? json_decode( get_option( 'iloveimg_images_to_restore' ), true ) : array();

        if ( (int) Ilove_Img_Wm_Resources::is_auto_watermark() === 1 && Ilove_Img_Wm_Resources::is_loggued() && (int) Ilove_Img_Wm_Resources::is_activated() === 1 && ! in_array( $attachment_id, $images_restore, true ) ) {
            wp_update_attachment_metadata( $attachment_id, $metadata );
            $this->async_watermark( $attachment_id );

        } elseif ( ! (int) Ilove_Img_Wm_Resources::is_auto_watermark() && (int) Ilove_Img_Wm_Resources::is_watermark_image() === 1 ) {
                $_wm_options                                 = json_decode( get_option( 'iloveimg_options_watermark' ), true );
                $_wm_options['iloveimg_field_autowatermark'] = 1;

                Ilove_Img_Wm_Resources::update_option( 'iloveimg_options_watermark', wp_json_encode( $_wm_options ) );
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
        $_wm_options = json_decode( get_option( 'iloveimg_options_watermark' ), true );
        unset( $_wm_options['iloveimg_field_autowatermark'] );

        Ilove_Img_Wm_Resources::update_option( 'iloveimg_options_watermark', wp_json_encode( $_wm_options ) );
        Ilove_Img_Wm_Resources::update_option( 'iloveimg_options_is_watermark_image', 1 );

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
				'action'   => 'ilove_img_wm_library',
				'id'       => $attachment_id,
                'imgnonce' => self::get_img_nonce(),
			),
            'cookies'   => $_COOKIE,
            'sslverify' => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
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
        $media = isset( $_REQUEST['media'] ) ? map_deep( wp_unslash( $_REQUEST['media'] ), 'absint' ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( $media ) {
            foreach ( $media as $attachment_id ) {
                $post = get_post( (int) $attachment_id );

                if ( strpos( $post->post_mime_type, 'image/' ) !== false ) {
                    $status_watermark = get_post_meta( $attachment_id, 'iloveimg_status_watermark', true );

                    if ( 0 === (int) $status_watermark ) {
                        $this->async_watermark( $attachment_id );
                    }
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
        if ( ! Ilove_Img_Wm_Resources::is_loggued() && get_current_screen()->parent_base !== 'iloveimg-admin-page' ) {
			?>
            <div class="notice notice-warning is-dismissible">
                <p><strong>iLoveIMG</strong> - <?php esc_html_e( 'Please you need to be logged or registered.', 'iloveimg-watermark' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=iloveimg-watermark-admin-page' ) ); ?>"><?php echo esc_html_x( 'Go to settings', 'button', 'iloveimg-watermark' ); ?></a></p>
            </div>
            <?php
        }

        if ( get_option( 'iloveimg_account_error' ) ) {
                $iloveimg_account_error = json_decode( get_option( 'iloveimg_account_error' ), true );
            if ( 'login' === $iloveimg_account_error['action'] ) :
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php esc_html_e( 'Your email or password is wrong.', 'iloveimg-watermark' ); ?></p>
                </div>
            <?php endif; ?>
            <?php if ( 'register' === $iloveimg_account_error['action'] ) : ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php esc_html_e( 'This email address has already been taken.', 'iloveimg-watermark' ); ?></p>
                </div>
            <?php endif; ?>
            <?php if ( 'register_limit' === $iloveimg_account_error['action'] ) : ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php esc_html_e( 'You have reached limit of different users to use this WordPress plugin. Please relogin with one of your existing users.', 'iloveimg-watermark' ); ?></p>
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

                if ( ! is_wp_error( $response ) ) {

                    if ( 200 === $response['response']['code'] ) {
                        $account = json_decode( $response['body'], true );

                        if ( $account['files_used'] >= $account['free_files_limit'] && $account['package_files_used'] >= $account['package_files_limit'] && (int) $account['subscription_files_used'] >= $account['subscription_files_limit'] ) {
                            ?>
                            <div class="notice notice-warning is-dismissible">
                                <p><strong>iLoveIMG</strong> - <?php esc_html_e( 'Please you need more credits.', 'iloveimg-watermark' ); ?> <a href="https://iloveapi.com/pricing" target="_blank"><?php echo esc_html_x( 'Buy more credits', 'button', 'iloveimg-watermark' ); ?></a></p>
                            </div>
                            <?php
                        }
                    } else {
                        ?>
                        <div class="notice notice-error is-dismissible">
                            <p><strong>iLoveIMG</strong> - <?php esc_html_e( 'We were unable to verify the status of your iloveAPI account. Please try again later.', 'iloveimg-watermark' ); ?></p>
                        </div>
                        <?php
                    }
                } else {
                    ?>
                    <div class="notice notice-error is-dismissible">
                        <p><strong>iLoveIMG</strong> - <?php esc_html_e( 'We were unable to verify the status of your iloveAPI account. Please try again later.', 'iloveimg-watermark' ); ?></p>
                    </div>
                    <?php
                }
			}
        }

        if ( get_current_screen()->parent_base === 'upload' && get_query_var( 'iloveimg-bulk-watermark' ) === 'success' ) {
            $files_success = get_transient( 'iloveimgwm_bulk_success' );

            foreach ( $files_success as $file ) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                    <?php
                    printf(
                        /* translators: %d: ID of File */
                        esc_html__( 'Image %d was watermarked correctly', 'iloveimg-watermark' ),
                        esc_html( $file )
                    );
					?>
                    </p>
                </div>
                <?php
            }
        }

        if ( get_current_screen()->parent_base === 'upload' && get_query_var( 'iloveimg-bulk-watermark' ) === 'error' ) {
            $files_with_errors = get_transient( 'iloveimgwm_bulk_errors' );

            foreach ( $files_with_errors as $file ) {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html( $file['message'] ); ?></p>
                </div>
                <?php
            }
        }

        if ( get_current_screen()->parent_base === 'upload' && get_query_var( 'iloveimg-bulk-watermark' ) === 'partial' ) {
            $files_success     = get_transient( 'iloveimgwm_bulk_success' );
            $files_with_errors = get_transient( 'iloveimgwm_bulk_errors' );

            foreach ( $files_success as $file ) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                    <?php
                    printf(
                        /* translators: %d: ID of File */
                        esc_html__( 'Image %d was watermarked correctly', 'iloveimg-watermark' ),
                        esc_html( $file )
                    );
					?>
                    </p>
                </div>
                <?php
            }

			foreach ( $files_with_errors as $file ) {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html( $file['message'] ); ?></p>
                </div>
                <?php
            }
        }
    }

    /**
     * Display additional iLoveIMG information in the WordPress media library.
     *
     * This method is responsible for adding a section to display iLoveIMG-related information for media attachments in the WordPress media library. It provides details about the watermarking status and a link to view watermark details.
     * This method is typically used to enhance the WordPress media library by adding iLoveIMG-specific details and is called within the `attachment_submitbox_misc_actions` hook.
     *
     * @since 1.0.0
     * @access public
     * @param \WP_Post $post Post object.
     */
    public function show_media_info( $post ) {
        $options = json_decode( get_option( 'iloveimg_options_watermark' ), true );

        if ( in_array( $post->post_mime_type, self::$accepted_file_format, true ) && isset( $options['iloveimg_field_watermark_activated'] ) ) {

            echo '<div class="misc-pub-section iloveimg-watermark-images">';
            echo '<h4>';
            echo esc_html_x( 'iLoveIMG Watermark', 'Subtitle for individual page of the file', 'iloveimg-watermark' );
            echo '</h4>';
            echo '<div class="iloveimg-container">';
            echo '<table><tr><td>';
            $status_watermark = get_post_meta( $post->ID, 'iloveimg_status_watermark', true );

            Ilove_Img_Wm_Resources::get_sizes_watermarked( $post->ID );

            if ( 2 === (int) $status_watermark ) {
                Ilove_Img_Wm_Resources::render_watermark_details( $post->ID );
                Ilove_Img_Wm_Resources::render_button_restore( $post->ID );
            } else {
                Ilove_Img_Wm_Resources::get_status_of_column( $post->ID );
            }

            echo '</td></tr></table>';
            echo '</div>';
            echo '</div>';
        }
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

            if ( strpos( $plugin_file, 'iloveimg' ) || strpos( $plugin_file, 'ilove-img' ) ) {

                if ( is_plugin_active( $plugin_file ) ) {

                    if ( strpos( $plugin_file, 'compress' ) !== false || 'iloveimg/ilove-img-compress.php' === $plugin_file ) {
                        $iloveimg_compress_found = true;

                        return $iloveimg_compress_found;
                    }
                }
            }
        }

        return $iloveimg_compress_found;
    }

    /**
     * Return Nonce security code.
     *
     * @since 1.0.4
     * @access public
     */
    public static function get_img_nonce() {
        return self::$img_nonce;
    }

    /**
     * Handle the AJAX request to restore an watermarked/compressed image.
     *
     * This method is responsible for processing an AJAX request to restore an watermarked/compressed image. It checks for the presence of a backup folder, restores the original images from the backup, and removes associated metadata and options related to watermarked and compressed images.
     *
     * @since 2.1.0
     */
    public function ilove_img_restore() {

        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) ) ) {
            wp_send_json_error( __( 'Error processing your request. Invalid Nonce code', 'iloveimg-watermark' ), 401 );
        }

        if ( ! isset( $_POST['id'] ) ) {
            wp_send_json_error( __( 'Error processing your request. Invalid Image ID', 'iloveimg-watermark' ), 400 );
        }

        $attachment_id  = intval( $_POST['id'] );
        $images_restore = null !== get_option( 'iloveimg_images_to_restore', null ) ? json_decode( get_option( 'iloveimg_images_to_restore' ), true ) : array();
        $key_founded    = array_search( $attachment_id, $images_restore, true );

        if ( ! in_array( $attachment_id, $images_restore, true ) ) {
            wp_send_json_error( __( 'Sorry. There is no backup for this file', 'iloveimg-watermark' ), 404 );
        }

        Ilove_Img_Wm_Resources::rcopy( ILOVE_IMG_WM_BACKUP_FOLDER . basename( get_attached_file( $attachment_id ) ), get_attached_file( $attachment_id ) );

        Ilove_Img_Wm_Resources::regenerate_attachment_data( $attachment_id );

        delete_post_meta( $attachment_id, 'iloveimg_status_watermark' );
        delete_post_meta( $attachment_id, 'iloveimg_watermark' );
        delete_post_meta( $attachment_id, 'iloveimg_status_compress' );
        delete_post_meta( $attachment_id, 'iloveimg_compress' );

        if ( false !== $key_founded ) {
            unset( $images_restore[ $key_founded ] );
            wp_delete_file( ILOVE_IMG_WM_BACKUP_FOLDER . basename( get_attached_file( $attachment_id ) ) );
            Ilove_Img_Wm_Resources::update_option( 'iloveimg_images_to_restore', wp_json_encode( $images_restore ) );
        }

        wp_send_json_success( __( 'It was restored correctly', 'iloveimg-watermark' ), 200 );
    }

    /**
     * Add bulk action
     *
     * @since 2.2.10
     * @param array $actions An array of the available bulk actions.
     */
    public function add_bulk_watermark_action( $actions ) {

        if ( get_option( 'iloveimg_account' ) ) {
            $actions['iloveimg_watermark'] = _x( 'Watermark Images', 'button', 'iloveimg-watermark' );
        }

        return $actions;
    }

    /**
     * Handle bulk watermark action
     *
     * @since 2.2.10
     * @param string $redirect_to The redirect URL.
     * @param string $doaction The action being taken.
     * @param array  $post_ids An array of post IDs.
     */
    public function handle_bulk_watermark_action( $redirect_to, $doaction, $post_ids ) {
        if ( 'iloveimg_watermark' !== $doaction ) {
            return $redirect_to;
        }

        $iloveimg_process = new Ilove_Img_Wm_Process();

		$success_ids = array();
		$error_items = array();

		foreach ( $post_ids as $id ) {
			$image = $iloveimg_process->watermark( $id );

			if ( ! empty( $image['error'] ) ) {
				$error_items[] = array(
					'id'      => $id,
					'message' => $image['error_msg'],
				);
			} else {
				$success_ids[] = $id;
			}
		}

		set_transient( 'iloveimgwm_bulk_success', $success_ids, 600 );
		set_transient( 'iloveimgwm_bulk_errors', $error_items, 600 );

		$status = 'success';

		if ( ! empty( $error_items ) && ! empty( $success_ids ) ) {
			$status = 'partial';
		} elseif ( ! empty( $error_items ) ) {
			$status = 'error';
		}

		wp_safe_redirect(
            add_query_arg(
                array(
					'iloveimg-bulk-watermark' => $status,
                ),
                'upload.php'
            )
		);
		exit();
    }

    /**
     * Add custom query variables
     *
     * @since 2.2.10
     * @param array $qvars An array of query variables.
     */
    public function add_iloveimgwm_custom_query_vars( $qvars ) {
        $qvars[] = 'iloveimg-bulk-watermark';

        return $qvars;
    }
}