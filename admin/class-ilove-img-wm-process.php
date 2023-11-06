<?php
use Iloveimg\WatermarkImageTask;

/**
 * Class for handling image watermark and processing using the iLoveIMG API.
 *
 * This class is responsible for image watermark and processing tasks
 * using the iLoveIMG API. It provides methods for watermarked images
 * and performing various image-related operations.
 *
 * @since 1.0.0
 */
class Ilove_Img_Wm_Process {

    /**
     * Public project key for iLoveIMG API.
     *
     * @var string $proyect_public The public project key for the iLoveIMG API.
     *
     * @since 1.0.0
     * @access public
     */
    public $proyect_public = '';

    /**
     * Secret key for iLoveIMG API.
     *
     * @var string $secret_key The secret key for the iLoveIMG API.
     *
     * @since 1.0.0
     * @access public
     */
    public $secret_key = '';

    /**
     * Apply watermark to an image in various sizes.
     *
     * This function takes an image's ID, applies a watermark to it in different sizes, and updates the image's metadata with watermarking status and information.
     * It considers watermarking options and settings from the plugin's configuration.
     *
     * @param int $images_id The ID of the image to watermark.
     *
     * @return array|bool An array with watermarking information or false if an error occurs.
     */
    public function watermark( $images_id ) {
        global $_wp_additional_image_sizes, $wpdb;

        $images = array();
        try {

            if ( get_option( 'iloveimg_proyect' ) ) {
                $proyect              = explode( '#', get_option( 'iloveimg_proyect' ) );
                $this->proyect_public = $proyect[0];
                $this->secret_key     = $proyect[1];
            } elseif ( get_option( 'iloveimg_account' ) ) {
                $account              = json_decode( get_option( 'iloveimg_account' ), true );
                $this->proyect_public = $account['projects'][0]['public_key'];
                $this->secret_key     = $account['projects'][0]['secret_key'];
            }

            $files_processing = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = 'iloveimg_status_watermark' AND meta_value = 1" );

            $image_compress_processing = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = 'iloveimg_status_compress' AND meta_value = 1 AND post_id =  " . $images_id );

            if ( $files_processing < ILOVE_IMG_WM_NUM_MAX_FILES && 0 === $image_compress_processing ) {
                update_post_meta( $images_id, 'iloveimg_status_watermark', 1 ); // status compressing

                $_sizes = get_intermediate_image_sizes();

                array_unshift( $_sizes, 'full' );
                $_wm_options = unserialize( get_option( 'iloveimg_options_watermark' ) );

                if ( isset( $_wm_options['iloveimg_field_backup'] ) ) {
					if ( ! is_dir( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' ) ) {
						mkdir( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' );
					}
					$images_restore   = get_option( 'iloveimg_images_to_restore' ) ? unserialize( get_option( 'iloveimg_images_to_restore' ) ) : array();
					$images_restore[] = $images_id;
					update_option( 'iloveimg_images_to_restore', serialize( $images_restore ) );
                }

                foreach ( $_sizes as $_size ) {

                    $document_root = '';

                    if ( isset( $_SERVER['DOCUMENT_ROOT'] ) ) {
                        $document_root = sanitize_url( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) );
                    } elseif ( isset( $_SERVER['SCRIPT_NAME'] ) && isset( $_SERVER['SCRIPT_FILENAME'] ) ) {
                        $document_root = str_replace( sanitize_url( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ), '', sanitize_url( wp_unslash( $_SERVER['SCRIPT_FILENAME'] ) ) );
                    }

                    $image            = wp_get_attachment_image_src( $images_id, $_size );
                    $path_file        = $document_root . str_replace( site_url(), '', $image[0] );
                    $images[ $_size ] = array( 'watermarked' => null );
                    if ( in_array( $_size, $_wm_options['iloveimg_field_sizes'], true ) ) {
                        // if enable backup
                        if ( isset( $_wm_options['iloveimg_field_backup'] ) ) {

                            $new_path = ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' . str_replace( ILOVE_IMG_WM_UPLOAD_FOLDER, '', dirname( $path_file ) );
                            if ( ! is_dir( $new_path ) ) {
								mkdir( $new_path, 0777, true );
                            }
                            copy( $path_file, $new_path . '/' . basename( $path_file ) );
                        }

                        $my_task = new WatermarkImageTask( $this->proyect_public, $this->secret_key );
                        $file    = $my_task->addFile( $path_file );
                        if ( isset( $_wm_options['iloveimg_field_type'] ) ) {
                            $gravity = array( 'NorthWest', 'North', 'NorthEast', 'CenterWest', 'Center', 'CenterEast', 'SouthWest', 'South', 'SouthEast' );
                            if ( 'text' === $_wm_options['iloveimg_field_type'] ) {
                                $font_style = null;
                                if ( isset( $_wm_options['iloveimg_field_text_bold'] ) && isset( $_wm_options['iloveimg_field_text_italic'] ) ) {
									$font_style = 'Bold-Italic';
                                } elseif ( isset( $_wm_options['iloveimg_field_text_bold'] ) ) {
										$font_style = 'Bold';
								} elseif ( isset( $_wm_options['iloveimg_field_text_italic'] ) ) {
									$font_style = 'Italic';
                                }
                                $element = $my_task->addElement(
                                    array(
										'type'          => 'text',
										'text'          => isset( $_wm_options['iloveimg_field_text'] ) ? $_wm_options['iloveimg_field_text'] : 'Sample',
										'width_percent' => $_wm_options['iloveimg_field_scale'],
										'font_family'   => $_wm_options['iloveimg_field_text_family'],
										'font_style'    => $font_style,
										'font_weight'   => isset( $_wm_options['iloveimg_field_text_bold'] ) ? 'Bold' : null,
										'font_color'    => isset( $_wm_options['iloveimg_field_text_color'] ) ? $_wm_options['iloveimg_field_text_color'] : '#000',
										'transparency'  => $_wm_options['iloveimg_field_opacity'],
										'rotation'      => $_wm_options['iloveimg_field_rotation'],
										'gravity'       => isset( $_wm_options['iloveimg_field_position'] ) ? $gravity[ $_wm_options['iloveimg_field_position'] - 1 ] : 'Center',
										'mosaic'        => isset( $_wm_options['iloveimg_field_mosaic'] ) ? true : false,
										'vertical_adjustment_percent' => 2,
										'horizontal_adjustment_percent' => 2,
                                    )
                                );
                            } else {
                                $watermark = $my_task->addFileFromUrl( $_wm_options['iloveimg_field_image'] );
                                $element   = $my_task->addElement(
                                    array(
										'type'            => 'image',
										'text'            => isset( $_wm_options['iloveimg_field_text'] ) ? $_wm_options['iloveimg_field_text'] : 'Sample',
										'width_percent'   => $_wm_options['iloveimg_field_scale'],
										'server_filename' => $watermark->getServerFilename(),
										'transparency'    => $_wm_options['iloveimg_field_opacity'],
										'rotation'        => $_wm_options['iloveimg_field_rotation'],
										'gravity'         => isset( $_wm_options['iloveimg_field_position'] ) ? $gravity[ $_wm_options['iloveimg_field_position'] - 1 ] : 'Center',
										'mosaic'          => isset( $_wm_options['iloveimg_field_mosaic'] ) ? true : false,
										'vertical_adjustment_percent' => 2,
										'horizontal_adjustment_percent' => 2,
                                    )
                                );
                            }
                        }
                        $my_task->execute();
                        $my_task->download( dirname( $path_file ) );
                        $images[ $_size ]['watermarked'] = 1;
                        do_action( 'ilove_img_wm_completed', $images_id );

                    }
                }
                update_post_meta( $images_id, 'iloveimg_watermark', $images );
                update_post_meta( $images_id, 'iloveimg_status_watermark', 2 ); // status compressed
                return $images;

            } else {
                update_post_meta( $images_id, 'iloveimg_status_watermark', 3 ); // status queue
                sleep( 2 );
                return $this->watermark( $images_id );
            }
		} catch ( Exception $e ) {
            update_post_meta( $images_id, 'iloveimg_status_watermark', 0 );
            return false;
        }
        return false;
    }
}
