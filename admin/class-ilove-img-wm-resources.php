<?php

class Ilove_Img_Wm_Resources {

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

    public static function get_saving( $images ) {
        $initial = $compressed = 0;
        foreach ( $images as $image ) {
            if ( ! is_null( $image['watermarked'] ) ) {
                $initial    += $image['initial'];
                $compressed += $image['watermarked'];
            }
        }
        return round( 100 - ( ( $compressed * 100 ) / $initial ) );
    }

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

    public static function is_there_backup() {
        return is_dir( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' );
    }

    public static function folder_size( $dir ) {
        $size = 0;

        foreach ( glob( rtrim( $dir, '/' ) . '/*', GLOB_NOSORT ) as $each ) {
            $size += is_file( $each ) ? filesize( $each ) : self::folder_size( $each );
        }

        return $size;
    }

    public static function get_size_backup() {
        if ( is_dir( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' ) ) {
            $f = ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup';
            /*
            $io = popen ( '/usr/bin/du -sk ' . $f, 'r' );
            $size = fgets ( $io, 4096);
            $size = substr ( $size, 0, strpos ( $size, "\t" ) );*/
            $size = self::folder_size( $f );
            return ( $size / 1024 ) / 1024;
        } else {
            return 0;
        }
    }

    public static function is_auto_watermark() {
        $_wm_options = unserialize( get_option( 'iloveimg_options_watermark' ) );
        return ( isset( $_wm_options['iloveimg_field_autowatermark'] ) ) ? 1 : 0;
    }

    public static function is_watermark_image() {
        return get_option( 'iloveimg_options_is_watermark_image' ) ? 1 : 0;
    }

    public static function is_activated() {
        $_wm_options = unserialize( get_option( 'iloveimg_options_watermark' ) );
        return ( isset( $_wm_options['iloveimg_field_watermark_activated'] ) ) ? 1 : 0;
    }

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

    public static function get_status_of_column( $column_id ) {
        $post = get_post( $column_id );
        if ( strpos( $post->post_mime_type, 'image/jpg' ) !== false or strpos( $post->post_mime_type, 'image/jpeg' ) !== false or strpos( $post->post_mime_type, 'image/png' ) !== false or strpos( $post->post_mime_type, 'image/gif' ) !== false ) :
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

    public static function get_files_compressed() {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = 'iloveimg_watermark'" );
    }

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
        $query_img      = new WP_Query( $query_img_args );
        return (int) $query_img->post_count;
    }

    public static function get_files_sizes() {
        global $wpdb;
        $rows             = $wpdb->get_results( "SELECT * FROM $wpdb->postmeta WHERE meta_key = 'iloveimg_watermark'" );
        $total            = 0;
        $total_compressed = 0;
        foreach ( $rows as $row ) {
            $stadistics = unserialize( $row->meta_value );
            foreach ( $stadistics as $key => $value ) {
                $total            = $total + (int) $value['initial'];
                $total_compressed = $total_compressed + (int) $value['watermarked'];
            }
        }
        return array( $total, $total_compressed );
    }
}