<?php
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

        $sizes   = array();
        $sizes[] = array(
            'field_id' => 'full',
            'type'     => 'checkbox',
            'label'    => 'Original image',
            'default'  => true,
        );

        foreach ( get_intermediate_image_sizes() as $_size ) {
            if ( in_array( $_size, array( 'thumbnail', 'medium', 'medium_large', 'large' ) ) ) {
                $width  = get_option( "{$_size}_size_w" );
                $height = get_option( "{$_size}_size_h" );

            } elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
                $width  = $_wp_additional_image_sizes[ $_size ]['width'];
                $height = $_wp_additional_image_sizes[ $_size ]['height'];
            }

            $sizes[] = array(
                'field_id' => $_size,
                'type'     => 'checkbox',
                'label'    => $_size . ' (' . ( ( $width == '0' ) ? '?' : $width ) . 'x' . ( ( $height == '0' ) ? '?' : $height ) . ')',
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
        if ( is_dir( $dir ) ) {
            $files = scandir( $dir );

            foreach ( $files as $file ) {
				if ( $file != '.' && $file != '..' ) {
					self::rrmdir( "$dir/$file" );
				}
            }

            rmdir( $dir );

        } elseif ( file_exists( $dir ) ) {
			unlink( $dir );
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
        if ( is_dir( $src ) ) {
            mkdir( $dst );

            $files = scandir( $src );

            foreach ( $files as $file ) {
				if ( $file != '.' && $file != '..' ) {
					self::rcopy( "$src/$file", "$dst/$file" );
				}
            }
		} elseif ( file_exists( $src ) ) {
            copy( $src, $dst );
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
        $initial = $progress = 0;

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
        $_wm_options = unserialize( get_option( 'iloveimg_options_watermark' ) );
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
        return is_dir( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' );
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
        if ( is_dir( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' ) ) {
            $folder = ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup';

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
        $_wm_options = unserialize( get_option( 'iloveimg_options_watermark' ) );
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
        $_wm_options = unserialize( get_option( 'iloveimg_options_watermark' ) );
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
                if ( ! is_null( $image['watermarked'] ) ) {
                    ++$count;
                }
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
        $_sizes            = get_post_meta( $image_id, 'iloveimg_watermark', true );
        $images_compressed = self::get_sizes_watermarked( $image_id );

        ?>
        <div id="iloveimg_detaills_watermark_<?php echo $image_id; ?>" style="display:none;">
            <table class="table__details__sizes">
                <tr>
                    <th>Name</th><th>Watermark</th>
                    <?php
                    foreach ( $_sizes as $key => $size ) {
                        ?>
                        <tr>
                            <td><a href="<?php echo wp_get_attachment_image_url( $image_id, $key ); ?>"  target="_blank"><?php echo $key; ?></a></td>
                            <td>
                            <?php
							if ( isset( $size['watermarked'] ) ) {
								if ( $size['watermarked'] ) {
									echo 'Applied';
								} else {
									echo 'Not applied';
								}
							} else {
								echo 'Not applied';
							}
							?>
                                </td>
                            </tr>
                        <?php
                    }
                    ?>
                </tr>
            </table>
        </div>
        <p><a href="#TB_inline?&width=450&height=340&inlineId=iloveimg_detaills_watermark_<?php echo $image_id; ?>" class="thickbox iloveimg_sizes_compressed" title="<?php echo get_the_title( $image_id ); ?>"><?php echo $images_compressed; ?> sizes watermark applied</a></p>
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

        if ( strpos( $post->post_mime_type, 'image/jpg' ) !== false || strpos( $post->post_mime_type, 'image/jpeg' ) !== false || strpos( $post->post_mime_type, 'image/png' ) !== false || strpos( $post->post_mime_type, 'image/gif' ) !== false ) :

            $_sizes            = get_post_meta( $column_id, 'iloveimg_watermark', true );
            $status_watermark  = (int) get_post_meta( $column_id, 'iloveimg_status_watermark', true );
            $images_compressed = self::get_sizes_watermarked( $column_id );

            if ( $_sizes && $images_compressed ) :
                self::render_watermark_details( $column_id );
            else :
                ?>
                         
                    <?php if ( self::is_loggued() ) : ?>
						<?php if ( self::get_sizes_enabled() ) : ?>
                            <button type="button" class="iloveimg-watermark button button-small button-primary" data-id="<?php echo $column_id; ?>" <?php echo ( $status_watermark === 1 || $status_watermark === 3 ) ? 'disabled="disabled"' : ''; ?>>Watermark</button>
                            <img src="<?php echo plugins_url( '/assets/images/spinner.gif', __DIR__ ); ?>" width="20" height="20" style="<?php echo ( $status_watermark === 1 || $status_watermark === 3 ) ? '' : 'display: none;'; ?>; margin-top: 7px" />
                            <?php if ( $status_watermark === 3 ) : ?>
                                <!-- <p>In queue...</p> -->
                            <?php endif; ?>
                        <?php else : ?>
                            <a href="<?php echo admin_url( 'admin.php?page=iloveimg-watermark-admin-page' ); ?>" class="iloveimg_link">Go to settings</button>
							<?php
                        endif;
                    else :
						?>
                        <p>You need to be registered</p>
                        <a href="<?php echo admin_url( 'admin.php?page=iloveimg-watermark-admin-page' ); ?>" class="iloveimg_link">Go to settings</button>
						<?php
                    endif;
                    if ( $status_watermark === 1 || $status_watermark === 3 ) :
						?>
                        <div class="iloveimg_watermarking" style="display: none;" data-id="<?php echo $column_id; ?>"></div>
						<?php
                    endif;
            endif;
        endif;
    }

    /**
     * Get the count of posts or columns with compressed files (watermarked images).
     *
     * This method retrieves the count of posts or columns that have associated compressed files (watermarked images) in the database.
     *
     * @global wpdb $wpdb WordPress database access object.
     * @return int The count of posts or columns with compressed files.
     */
    public static function get_files_compressed() {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = 'iloveimg_watermark'" );
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
        $rows          = $wpdb->get_results( "SELECT * FROM $wpdb->postmeta WHERE meta_key = 'iloveimg_watermark'" );
        $total         = 0;
        $total_process = 0;

        foreach ( $rows as $row ) {
            $stadistics = unserialize( $row->meta_value );

            foreach ( $stadistics as $key => $value ) {
                $total         = $total + (int) $value['initial'];
                $total_process = $total_process + (int) $value['watermarked'];
            }
        }

        return array( $total, $total_process );
    }
}