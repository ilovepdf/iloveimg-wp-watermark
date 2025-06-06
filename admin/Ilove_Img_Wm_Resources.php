<?php
namespace Ilove_Img_Wm;

use WP_Query;
/**
 * Utility class for managing resources and functionality related to image watermark.
 *
 * This class serves as a utility for managing various resources and functionality
 * associated with image watermark within the iLoveIMG plugin. It includes methods
 * for handling image watermark resources, status, and related operations.
 *
 * @since 1.0.0
 */
class Ilove_Img_Wm_Resources {

    /**
     * Get an array of image size options for image type selection.
     *
     * This method retrieves an array of image size options to be used for image type selection
     * in the settings. It includes options for the original image as well as available image sizes.
     *
     * @return array An array of image size options.
     *
     * @since 1.0.0
     */
    public static function get_type_images() {
        global $_wp_additional_image_sizes;

        $sizes  = array();
        $width  = '';
        $height = '';

        $sizes[] = array(
            'field_id' => 'full',
            'type'     => 'checkbox',
            'label'    => _x( 'Original image', 'input checkbox', 'iloveimg-watermark' ),
            'default'  => true,
        );

        foreach ( get_intermediate_image_sizes() as $_size ) {
            if ( in_array( $_size, array( 'thumbnail', 'medium', 'medium_large', 'large' ), true ) ) {
                $width  = get_option( "{$_size}_size_w" );
                $height = get_option( "{$_size}_size_h" );

            } elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
                $width  = $_wp_additional_image_sizes[ $_size ]['width'];
                $height = $_wp_additional_image_sizes[ $_size ]['height'];
            }

            $sizes[] = array(
                'field_id' => $_size,
                'type'     => 'checkbox',
                'label'    => $_size . ' (' . ( ( '0' === $width ) ? '?' : $width ) . 'x' . ( ( '0' === $height ) ? '?' : $height ) . ')',
                'default'  => true,
            );
        }

        return $sizes;
    }

    /**
     * Recursively remove a directory and its contents.
     *
     * This method recursively deletes the specified directory and all its contents, including files and subdirectories.
     *
     * @param string $dir The path to the directory to be removed.
     */
    public static function rrmdir( $dir ) {

        if ( ! WP_Filesystem() ) {
			return new \WP_Error(
				'Unable Filesystem',
				esc_html__( 'Unable to connect to the filesystem', 'iloveimg-watermark' )
			);
		}

        global $wp_filesystem;

        if ( is_dir( $dir ) ) {
            $files = scandir( $dir );

            foreach ( $files as $file ) {
				if ( '.' !== $file && '..' !== $file ) {
					self::rrmdir( "$dir/$file" );
				}
            }

            $wp_filesystem->rmdir( $dir );

        } elseif ( file_exists( $dir ) ) {
			wp_delete_file( $dir );
        }
    }

    /**
     * Recursively copy a directory and its contents to a destination directory.
     *
     * This method recursively copies the contents of the source directory to the destination directory, including files and subdirectories.
     *
     * @param string $src The source directory to be copied.
     * @param string $dst The destination directory where the contents will be copied to.
     */
    public static function rcopy( $src, $dst ) {

        if ( ! WP_Filesystem() ) {
			return new \WP_Error(
				'Unable Filesystem',
				esc_html__( 'Unable to connect to the filesystem', 'iloveimg-watermark' )
			);
		}

        global $wp_filesystem;

        if ( is_dir( $src ) ) {
            $wp_filesystem->mkdir( $dst );

            $files = scandir( $src );

            foreach ( $files as $file ) {
				if ( '.' !== $file && '..' !== $file ) {
					self::rcopy( "$src/$file", "$dst/$file" );
				}
            }
		} elseif ( file_exists( $src ) ) {
            $base_file_name             = basename( $src );
            $compare_dst_base_file_name = basename( $dst );

            if ( ! file_exists( $dst ) ) {
                $wp_filesystem->mkdir( $dst );
            }

            if ( $compare_dst_base_file_name === $base_file_name ) {
                copy( $src, $dst );
            } else {
                copy( $src, $dst . '/' . $base_file_name );
            }
        }
    }

    /**
     * Calculate the saving percentage achieved by watermarking images.
     *
     * This method calculates the percentage of space saved by comparing the sizes of the original images
     * with watermarking to the sizes of watermarked images. It takes an array of image data as input.
     *
     * @param array $images An array of image data containing initial and watermarked image sizes.
     * @return float The percentage of space saved by watermarking the images.
     */
    public static function get_saving( $images ) {
        $initial  = 0;
        $progress = 0;

        foreach ( $images as $image ) {
            if ( ! is_null( $image['watermarked'] ) ) {
                $initial  += $image['initial'];
                $progress += $image['watermarked'];
            }
        }

        return round( 100 - ( ( $progress * 100 ) / $initial ) );
    }

    /**
     * Get the count of enabled image sizes for watermarking.
     *
     * This method retrieves the count of enabled image sizes for watermarking from the plugin options.
     *
     * @return int The count of image sizes that are enabled for watermarking.
     */
    public static function get_sizes_enabled() {
        $_wm_options = json_decode( get_option( 'iloveimg_options_watermark' ), true );
        $image_sizes = $_wm_options['iloveimg_field_sizes'];
        $count       = 0;

        foreach ( $image_sizes as $image ) {
            if ( $image ) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Check if a backup directory exists.
     *
     * This method checks for the existence of a backup directory within the specified folder.
     *
     * @return bool True if the backup directory exists, false otherwise.
     */
    public static function is_there_backup() {
        return is_dir( ILOVE_IMG_WM_BACKUP_FOLDER );
    }

    /**
     * Calculate the size of a folder and its contents recursively.
     *
     * This method calculates the total size of a folder and all its contents, including files and subdirectories.
     *
     * @param string $dir The path to the folder for which the size should be calculated.
     * @return int The total size of the folder and its contents in bytes.
     */
    public static function folder_size( $dir ) {
        $size = 0;

        foreach ( glob( rtrim( $dir, '/' ) . '/*', GLOB_NOSORT ) as $each ) {
            $size += is_file( $each ) ? filesize( $each ) : self::folder_size( $each );
        }

        return $size;
    }

    /**
     * Get the size of the backup folder in megabytes (MB).
     *
     * This method checks if a backup directory exists and calculates its size in megabytes.
     *
     * @return float The size of the backup folder in megabytes (MB). Returns 0 if the backup directory doesn't exist.
     */
    public static function get_size_backup() {
        if ( is_dir( ILOVE_IMG_WM_BACKUP_FOLDER ) ) {
            $folder = ILOVE_IMG_WM_BACKUP_FOLDER;

            $size = self::folder_size( $folder );

            return ( $size / 1024 ) / 1024;
        } else {
            return 0;
        }
    }

    /**
     * Check if automatic watermarking is enabled.
     *
     * This method retrieves the watermarking options and checks if automatic watermarking is enabled in the plugin's settings.
     *
     * @return int Returns 1 if automatic watermarking is enabled, and 0 if it's not.
     */
    public static function is_auto_watermark() {
        $_wm_options = json_decode( get_option( 'iloveimg_options_watermark' ), true );
        return ( isset( $_wm_options['iloveimg_field_autowatermark'] ) ) ? 1 : 0;
    }

    /**
     * Check if an image is configured as a watermark.
     *
     * This method checks whether an image has been configured as a watermark in the plugin's settings.
     *
     * @return int Returns 1 if an image is configured as a watermark, and 0 if it's not.
     */
    public static function is_watermark_image() {
        return get_option( 'iloveimg_options_is_watermark_image' ) ? 1 : 0;
    }

    /**
     * Check if watermarking is activated.
     *
     * This method retrieves the watermarking options and checks if watermarking is activated in the plugin's settings.
     *
     * @return int Returns 1 if watermarking is activated, and 0 if it's not.
     */
    public static function is_activated() {
        $_wm_options = json_decode( get_option( 'iloveimg_options_watermark' ), true );
        return ( isset( $_wm_options['iloveimg_field_watermark_activated'] ) ) ? 1 : 0;
    }

    /**
     * Get the count of watermarked images for a specific post or column.
     *
     * This method retrieves the count of images that have been watermarked for a specific post or column.
     *
     * @param int $column_id The post or column ID for which the watermarked image count should be determined.
     * @return int The count of watermarked images for the specified post or column.
     */
    public static function get_sizes_watermarked( $column_id ) {
        $images = get_post_meta( $column_id, 'iloveimg_watermark', true );
        $count  = 0;

        if ( ! $images ) {
            return $count;
        }

        foreach ( $images as $image ) {
            if ( isset( $image['watermarked'] ) ) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Check if a user is logged in to an Iloveimg account.
     *
     * This method checks if a user is logged in to an Iloveimg account by inspecting plugin settings.
     *
     * @return bool Returns true if the user is logged in, and false if they are not.
     */
    public static function is_loggued() {
        if ( get_option( 'iloveimg_account' ) ) {
            $account = json_decode( get_option( 'iloveimg_account' ), true );
            if ( array_key_exists( 'error', $account ) ) {
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * Render watermark details for a specific image.
     *
     * This method renders details about watermarking for a specific image, including the applied watermark status for different sizes.
     *
     * @param int $image_id The ID of the image for which watermark details should be rendered.
     */
    public static function render_watermark_details( $image_id ) {
        $_sizes             = get_post_meta( $image_id, 'iloveimg_watermark', true );
        $images_watermarked = self::get_sizes_watermarked( $image_id );

        ?>
        <div id="iloveimg_detaills_watermark_<?php echo (int) $image_id; ?>" style="display:none;">
            <table class="table__details__sizes">
                <tr>
                    <th><?php echo esc_html_x( 'Name', 'column name', 'iloveimg-watermark' ); ?></th>
                    <th><?php echo esc_html_x( 'Watermark', 'column name', 'iloveimg-watermark' ); ?></th>
                    <?php
                    if ( $_sizes ) {
                        foreach ( $_sizes as $key => $size ) {
                            ?>
                            <tr>
                                <td><a href="<?php echo esc_url( wp_get_attachment_image_url( $image_id, $key ) ); ?>"  target="_blank"><?php echo esc_html( $key ); ?></a></td>
                                <td>
                                <?php
                                if ( isset( $size['watermarked'] ) ) {
                                    if ( $size['watermarked'] ) {
                                        echo esc_html_x( 'Applied', 'Watermark applied to an image', 'iloveimg-watermark' );
                                    } else {
                                        echo esc_html_x( 'Not applied', 'Watermark not applied to an image', 'iloveimg-watermark' );
                                    }
                                } else {
                                    echo esc_html_x( 'Not applied', 'Watermark not applied to an image', 'iloveimg-watermark' );
                                }
                                ?>
                                    </td>
                                </tr>
                            <?php
                        }
                    }
                    ?>
                </tr>
            </table>
        </div>
        <p>
            <a href="#TB_inline?&width=550&height=440&inlineId=iloveimg_detaills_watermark_<?php echo (int) $image_id; ?>" class="thickbox iloveimg_sizes_watermarked" title="<?php echo esc_html( get_the_title( $image_id ) ); ?>">
                <?php
                printf(
                    /* translators: %s: Number of watermarked image sizes */
                    esc_html__( 'Watermark applied to %s sizes', 'iloveimg-watermark' ),
                    (int) $images_watermarked
                );
                ?>
            </a>
        </p>
        <?php
    }

    /**
     * Get the status and actions related to watermarking for a specific post or column.
     *
     * This method retrieves the status and actions related to watermarking for a specific post or column, including the ability to add watermarks, view details, and manage settings.
     *
     * @param int $column_id The ID of the post or column for which watermarking status and actions should be determined.
     */
    public static function get_status_of_column( $column_id ) {
        $post = get_post( $column_id );

        $img_nonce = Ilove_Img_Wm_Plugin::get_img_nonce();

        if ( strpos( $post->post_mime_type, 'image/jpg' ) !== false || strpos( $post->post_mime_type, 'image/jpeg' ) !== false || strpos( $post->post_mime_type, 'image/png' ) !== false || strpos( $post->post_mime_type, 'image/gif' ) !== false ) :

            $_sizes             = get_post_meta( $column_id, 'iloveimg_watermark', true );
            $status_watermark   = (int) get_post_meta( $column_id, 'iloveimg_status_watermark', true );
            $images_watermarked = self::get_sizes_watermarked( $column_id );

            if ( $_sizes && $images_watermarked ) :
                self::render_watermark_details( $column_id );
                self::render_button_restore( $column_id );
            else :
                ?>
                         
                    <?php if ( self::is_loggued() ) : ?>
						<?php if ( self::get_sizes_enabled() ) : ?>
                            <button type="button" class="iloveimg-watermark button button-small button-primary" data-imgnonce="<?php echo sanitize_key( wp_unslash( $img_nonce ) ); ?>" data-id="<?php echo (int) $column_id; ?>" <?php echo ( 1 === $status_watermark || 3 === $status_watermark ) ? 'disabled="disabled"' : ''; ?>><?php echo esc_html_x( 'Watermark', 'button', 'iloveimg-watermark' ); ?></button>
                            <img src="<?php echo esc_url( plugins_url( '/assets/images/spinner.gif', __DIR__ ) ); ?>" width="20" height="20" style="<?php echo ( 1 === $status_watermark || 3 === $status_watermark ) ? '' : 'display: none;'; ?>; margin-top: 7px" />
                            <?php if ( 3 === $status_watermark ) : ?>
                                <!-- <p>In queue...</p> -->
                            <?php endif; ?>
                        <?php else : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=iloveimg-watermark-admin-page' ) ); ?>" class="iloveimg_link"><?php echo esc_html_x( 'Go to settings', 'button', 'iloveimg-watermark' ); ?></button>
							<?php
                        endif;
                    else :
						?>
                        <p><?php esc_html_e( 'You need to be registered', 'iloveimg-watermark' ); ?></p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=iloveimg-watermark-admin-page' ) ); ?>" class="iloveimg_link"><?php echo esc_html_x( 'Go to settings', 'button', 'iloveimg-watermark' ); ?></button>
						<?php
                    endif;
                    if ( 1 === $status_watermark || 3 === $status_watermark ) :
						?>
                        <div class="iloveimg_watermarking" style="display: none;" data-id="<?php echo (int) $column_id; ?>"></div>
						<?php
                    endif;
            endif;
        endif;
    }

    /**
     * Get the count of posts or columns with watermark files (watermarked images).
     *
     * This method retrieves the count of posts or columns that have associated watermark files (watermarked images) in the database.
     *
     * @global wpdb $wpdb WordPress database access object.
     * @return int The count of posts or columns with watermark files.
     */
    public static function get_watermarked_files() {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = 'iloveimg_watermark'" ); // phpcs:ignore
    }

    /**
     * Get the total count of images in the WordPress site.
     *
     * This method retrieves the total count of images in the WordPress site, including images with specified MIME types.
     *
     * @return int The total count of images in the site.
     */
    public static function get_total_images() {
        $query_img_args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'gif'          => 'image/gif',
				'png'          => 'image/png',
			),
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
        );

        $query_img = new WP_Query( $query_img_args );

        return (int) $query_img->post_count;
    }

    /**
     * Get the statistics for file sizes of watermarked images.
     *
     * This method retrieves statistics for file sizes of watermarked images, including the total initial file sizes and the total processed (watermarked) file sizes.
     *
     * @global wpdb $wpdb WordPress database access object.
     * @return array An array containing two elements: the total initial file sizes and the total processed (watermarked) file sizes.
     */
    public static function get_files_sizes() {
        global $wpdb;
        $rows          = $wpdb->get_results( "SELECT * FROM $wpdb->postmeta WHERE meta_key = 'iloveimg_watermark'" ); // phpcs:ignore
        $total         = 0;
        $total_process = 0;

        foreach ( $rows as $row ) {
            $stadistics = json_decode( $row->meta_value, true );

            foreach ( $stadistics as $key => $value ) {
                $total         = $total + (int) $value['initial'];
                $total_process = $total_process + (int) $value['watermarked'];
            }
        }

        return array( $total, $total_process );
    }

    /**
     * Render the restore button for an image.
     *
     * This method generates and displays button restore of image compression for a specific attachment
     * identified by its image ID.
     *
     * @param int $image_id The ID of the attachment in the WordPress media library.
     *
     * @since 2.1.0
     */
    public static function render_button_restore( $image_id ) {
        $iloveimg_options_watermark = json_decode( get_option( 'iloveimg_options_watermark' ), true );
        $iloveimg_options_compress  = json_decode( get_option( 'iloveimg_options_compress' ), true );
        $backup_activated           = false;

        if ( ( isset( $iloveimg_options_compress['iloveimg_field_backup'] ) && 'on' === $iloveimg_options_compress['iloveimg_field_backup'] ) || ( isset( $iloveimg_options_watermark['iloveimg_field_backup'] ) && 'on' === $iloveimg_options_watermark['iloveimg_field_backup'] ) ) {
            $backup_activated = true;
        }

        $images_restore = get_option( 'iloveimg_images_to_restore' ) ? json_decode( get_option( 'iloveimg_images_to_restore' ), true ) : array();
        $img_nonce      = Ilove_Img_Wm_Plugin::get_img_nonce();

        ?>
            <?php if ( $backup_activated && in_array( $image_id, $images_restore, true ) ) : ?>
                <div class="iloveimg-watermark iloveimg_restore_button_wrapper">
                    <button class="iloveimg_restore_button button button-secondary" data-id="<?php echo intval( $image_id ); ?>" data-action="ilove_img_wm_restore">
                        <?php echo esc_html_x( 'Restore original file', 'button', 'iloveimg-watermark' ); ?>
                    </button>
                    <br/>
                    <input type="hidden" id="_wpnonce" name="_wpnonce_iloveimg_wm_restore" value="<?php echo esc_html( $img_nonce ); ?>">
                    <p class="loading iloveimg-status" style="display: none; margin-top: 5px;">
                        <span>
                            <?php echo esc_html_x( 'Loading...', 'The file is being processed', 'iloveimg-watermark' ); ?>
                        </span>
                    </p>
                    <p class="error iloveimg-status" style="margin-top: 5px;">
                        <span>
                            <?php echo esc_html_x( 'Error', 'File processing had an error', 'iloveimg-watermark' ); ?>
                        </span>
                    </p>
                    <p class="success iloveimg-status" style="margin-top: 5px;">
                        <span>
                            <?php echo esc_html_x( 'Completed, please refresh the page.', 'File processing was successful', 'iloveimg-watermark' ); ?>
                        </span>
                    </p>
                </div>
            <?php endif; ?>
        <?php
    }

    /**
     * Regenerate attachment metadata
     *
     * @since      2.1.0
     * @param int $attachment_id File ID.
     */
    public static function regenerate_attachment_data( $attachment_id ) {

        if ( ! $attachment_id ) {
            return;
        }

        $file_path = get_attached_file( $attachment_id ); // Get File path of attachment
        $metadata  = wp_generate_attachment_metadata( $attachment_id, $file_path ); // Regenerate attachment metadata

        wp_update_attachment_metadata( $attachment_id, $metadata ); // Update new attachment metadata
    }

    /**
	 * Update option, works with multisite if enabled
	 *
	 * @since  2.2.4
	 * @param  string    $option Name of the option to update. Expected to not be SQL-escaped.
	 * @param  mixed     $value Option value. Must be serializable if non-scalar. Expected to not be SQL-escaped.
     * @param  bool      $update_all_sites Optional. Whether to update all sites in the network.
	 * @param  bool|null $autoload Optional. Whether to load the option when WordPress starts up. Accepts a boolean, or null.
	 */
	public static function update_option( $option, $value, $update_all_sites = false, $autoload = null ) {

		if ( ! is_multisite() ) {
			update_option( $option, $value, $autoload );
			return;
		}

        if ( ! $update_all_sites ) {
            self::switch_update_blog( get_current_blog_id(), $option, $value, $autoload );
            return;
        }

        $sites = get_sites();
        foreach ( $sites as $site ) {
            self::switch_update_blog( (int) $site->blog_id, $option, $value, $autoload );
        }
	}

    /**
     * Switch to blog and update option
     *
     * @since  2.2.4
     * @param  int       $blog_id ID of the blog to switch to.
     * @param  string    $option Name of the option to update.
     * @param  mixed     $value Option value.
     * @param  bool|null $autoload Whether to load the option when WordPress starts up.
     */
    private static function switch_update_blog( $blog_id, $option, $value, $autoload ) {
        switch_to_blog( $blog_id );
        update_option( $option, $value, $autoload );
        restore_current_blog();
    }
}