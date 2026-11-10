<?php
namespace Ilove_Img_Wm;

use Iloveimg\WatermarkImageTask;
use Iloveimg\Exceptions\ExtendedException;

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
     * Whether this instance currently holds the queue lock.
     *
     * Prevents releasing a lock acquired by a concurrent process ("lock theft")
     * when release_queue_lock() is called on an error path that runs after the
     * lock was never acquired, or after it was already released.
     *
     * @var bool $lock_held
     *
     * @since 3.0.0
     * @access private
     */
    private bool $lock_held = false;

    /**
     * Gravity values accepted by the iLoveIMG API, indexed by the position stored in the settings.
     *
     * @var string[] $gravity_map The gravity value for each of the nine selectable positions.
     *
     * @since 3.0.0
     * @access private
     */
    private static $gravity_map = array( 'NorthWest', 'North', 'NorthEast', 'CenterWest', 'Center', 'CenterEast', 'SouthWest', 'South', 'SouthEast' );

    /**
     * Apply watermark to an image in various sizes.
     *
     * This function takes an image's ID, applies a watermark to it in different sizes, and updates the image's metadata with watermarking status and information.
     * It considers watermarking options and settings from the plugin's configuration.
     *
     * @param int $images_id The ID of the image to watermark.
     *
     * @return array|bool|\Exception An array with watermarking information or false if an error occurs.
     */
    public function watermark( $images_id ) {

        global $wpdb;

        $images         = array();
        $file_mime_type = get_post_mime_type( $images_id );

        try {

            if ( ! in_array( $file_mime_type, Ilove_Img_Wm_Plugin::$accepted_file_format, true ) ) {
                return array(
                    'error'     => true,
                    'error_msg' => sprintf(
                        /* translators: %d: ID of File */
                        __( 'File %d is not a valid image.', 'iloveimg-watermark' ),
                        $images_id
                    ),
                );
            }

            if ( get_option( 'iloveimg_proyect' ) ) {
                $proyect              = explode( '#', get_option( 'iloveimg_proyect' ) );
                $this->proyect_public = $proyect[0];
                $this->secret_key     = $proyect[1];
            } elseif ( get_option( 'iloveimg_account' ) ) {
                $account              = json_decode( get_option( 'iloveimg_account' ), true );
                $this->proyect_public = $account['projects'][0]['public_key'];
                $this->secret_key     = $account['projects'][0]['secret_key'];
            }

            // REQ-C1: Validate watermark configuration before consuming a queue slot.
            $_wm_options = json_decode( get_option( 'iloveimg_options_watermark' ), true );
            $_wm_sizes   = $this->get_configured_sizes( $_wm_options );

            if ( empty( $_wm_sizes ) ) {
                update_post_meta( $images_id, 'iloveimg_status_watermark', 0 );
                return array(
                    'error'     => true,
                    'error_msg' => __( 'No watermark is configured. Set up the watermark in the plugin settings before applying it.', 'iloveimg-watermark' ),
                );
            }

            // REQ-D1: Acquire the atomic queue lock before entering the critical section.
            if ( ! $this->acquire_queue_lock() ) {
                update_post_meta( $images_id, 'iloveimg_status_watermark', 3 );
                return array(
                    'error'     => true,
                    'error_msg' => __( 'Another image is still being processed. Please try again in a moment.', 'iloveimg-watermark' ),
                );
            }

            // Critical section: release stalled → count → compress-skip → mark status=1.
            $this->release_stalled_processes();

            $files_processing = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = 'iloveimg_status_watermark' AND meta_value = 1" ); // phpcs:ignore

            $image_compress_processing = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = 'iloveimg_status_compress' AND meta_value = 1 AND post_id =  " . $images_id ); // phpcs:ignore

            if ( $files_processing < ILOVE_IMG_WM_NUM_MAX_FILES && 0 === (int) $image_compress_processing ) {
                update_post_meta( $images_id, 'iloveimg_status_watermark', 1 ); // status watermark
                update_post_meta( $images_id, 'iloveimg_watermark_started', time() );

                // REQ-D1: Release the lock immediately after status=1 is committed.
                // The slow API work (upload/execute/download) runs outside the critical section.
                $this->release_queue_lock();

                $_sizes = get_intermediate_image_sizes();

                array_unshift( $_sizes, 'full' );

                // $_wm_options and $_wm_sizes were already decoded above (REQ-C1); reuse them here.
                $options_compress = json_decode( get_option( 'iloveimg_options_compress' ), true );
                $images_restore   = get_option( 'iloveimg_images_to_restore' ) ? json_decode( get_option( 'iloveimg_images_to_restore' ), true ) : array();

                if ( ( isset( $options_compress['iloveimg_field_backup'] ) || isset( $_wm_options['iloveimg_field_backup'] ) ) && ! in_array( $images_id, $images_restore, true ) ) {

                    $attached_file = get_attached_file( $images_id );

                    Ilove_Img_Wm_Resources::rcopy( $attached_file, ILOVE_IMG_WM_BACKUP_FOLDER );

					$images_restore[] = $images_id;

                    $images_restore = array_unique( $images_restore );

                    Ilove_Img_Wm_Resources::update_option( 'iloveimg_images_to_restore', wp_json_encode( $images_restore, JSON_FORCE_OBJECT ) );
                }

                foreach ( $_sizes as $_size ) {

                    $image = wp_get_attachment_image_src( $images_id, $_size );

                    // REQ-A2: Skip missing or invalid sizes without a fatal.
                    if ( ! is_array( $image ) || empty( $image[0] ) ) {
                        continue;
                    }

                    $images[ $_size ] = array( 'watermarked' => null );

                    // REQ-F2: Resolve the absolute filesystem path using wp_upload_dir(),
                    // which is reliable in async (admin-ajax) context unlike $_SERVER['DOCUMENT_ROOT'].
                    $path_file = $this->resolve_source_path( $image[0] );

                    // REQ-F1: Validate source file before sending to the API.
                    if ( ! $this->is_valid_source_file( $path_file ) ) {
                        continue;
                    }

                    if ( in_array( $_size, $_wm_sizes, true ) ) {

                        $my_task = new WatermarkImageTask( $this->proyect_public, $this->secret_key );
                        $file    = $my_task->addFile( $path_file );

                        if ( isset( $_wm_options['iloveimg_field_type'] ) ) {
                            $element = $my_task->addElement( $this->get_element_params( $_wm_options, $my_task ) ); // @phpstan-ignore-line
                        }

                        $execute_task = $my_task->execute();
                        $my_task->download( dirname( $path_file ) );
                        $images[ $_size ]['watermarked'] = 1;
                        do_action( 'ilove_img_wm_completed', $images_id );

                    }
                }

                update_post_meta( $images_id, 'iloveimg_watermark', $images );
                update_post_meta( $images_id, 'iloveimg_status_watermark', 2 ); // status watermarked
                delete_post_meta( $images_id, 'iloveimg_watermark_started' );

                return array(
                    'error'  => false,
                    'images' => $images,
                );

            } else {
                // Gate not passed: release the lock before deferring to status=3.
                $this->release_queue_lock();

                update_post_meta( $images_id, 'iloveimg_status_watermark', 3 ); // status queue

                return array(
                    'error'     => true,
                    'error_msg' => __( 'Another image is still being processed. Please try again in a moment.', 'iloveimg-watermark' ),
                );
            }
        } catch ( \Throwable $e ) {
            update_post_meta( $images_id, 'iloveimg_status_watermark', 0 );
            delete_post_meta( $images_id, 'iloveimg_watermark_started' );

            // REQ-D1: Release the queue lock on any failure path so the gate is never permanently locked.
            $this->release_queue_lock();

            return array(
                'error'     => true,
                'error_msg' => $this->get_error_message( $e ),
            );
        }
    }

    /**
     * Return the configured watermark sizes from the plugin options.
     *
     * @param mixed $wm_options The decoded iloveimg_options_watermark option (expected array).
     *
     * @return array The list of configured sizes, or an empty array when options are corrupt or no sizes are set.
     *
     * @since 3.0.0
     */
    private function get_configured_sizes( $wm_options ): array {
        if ( is_array( $wm_options )
            && isset( $wm_options['iloveimg_field_sizes'] )
            && is_array( $wm_options['iloveimg_field_sizes'] )
            && ! empty( $wm_options['iloveimg_field_sizes'] )
        ) {
            return $wm_options['iloveimg_field_sizes'];
        }
        return array();
    }

    /**
     * Attempt to acquire the site-wide watermark queue lock.
     *
     * Uses add_option() as an atomic INSERT — the same technique WordPress core's
     * WP_Upgrader::create_lock() uses. A stale lock older than ILOVE_IMG_WM_PROCESS_TIMEOUT
     * is force-reclaimed and the acquire is retried once.
     *
     * @return bool True when the lock was acquired, false when another process holds it.
     *
     * @since 3.0.0
     */
    private function acquire_queue_lock(): bool {
        $acquired = add_option( 'iloveimg_wm_queue_lock', time(), '', false );
        if ( $acquired ) {
            $this->lock_held = true;
            return true;
        }

        // Lock already exists: reclaim it if the stored timestamp has exceeded the timeout.
        $lock_time = (int) get_option( 'iloveimg_wm_queue_lock' );
        if ( ILOVE_IMG_WM_PROCESS_TIMEOUT <= ( time() - $lock_time ) ) {
            delete_option( 'iloveimg_wm_queue_lock' );
            $acquired        = add_option( 'iloveimg_wm_queue_lock', time(), '', false );
            $this->lock_held = $acquired;
            return $acquired;
        }

        return false;
    }

    /**
     * Release the site-wide watermark queue lock.
     *
     * @return void
     *
     * @since 3.0.0
     */
    private function release_queue_lock(): void {
        if ( $this->lock_held ) {
            delete_option( 'iloveimg_wm_queue_lock' );
            $this->lock_held = false;
        }
    }

    /**
     * Resolve an attachment image URL to an absolute filesystem path.
     *
     * Uses wp_upload_dir() which is correct in both sync and async (admin-ajax)
     * contexts, unlike $_SERVER['DOCUMENT_ROOT'] which may be absent in async requests.
     *
     * @param string $image_url The public URL of the attachment image.
     *
     * @return string The absolute filesystem path, or an empty string when the URL is outside the uploads directory.
     *
     * @since 3.0.0
     */
    private function resolve_source_path( string $image_url ): string {
        $uploads = wp_upload_dir();
        if ( false === strpos( $image_url, $uploads['baseurl'] ) ) {
            return '';
        }
        return str_replace( $uploads['baseurl'], $uploads['basedir'], $image_url );
    }

    /**
     * Validate that a resolved source path points to a usable file.
     *
     * @param string $path The absolute filesystem path to validate.
     *
     * @return bool True when the path is absolute, the file exists, and is non-empty.
     *
     * @since 3.0.0
     */
    private function is_valid_source_file( string $path ): bool {
        return '' !== $path
            && path_is_absolute( $path )
            && file_exists( $path )
            && 0 < filesize( $path );
    }

    /**
     * Build the validated watermark element sent to the iLoveIMG API.
     *
     * WHY type=image always:
     * The iLoveIMG developer API's `watermarkimage` tool returns `DamagedFile` for every
     * `type=text` element (proven exhaustively — see
     * openspec/changes/fix-watermark-php83-processing/watermark-api-finding.md), while
     * `type=image` and `compressimage` succeed on the same file.
     *
     * iloveimg.com's own watermark tool never sends `type=text` to the API: it rasterises
     * the text client-side to a transparent PNG and submits a `type=image` element. We
     * mirror that behaviour here — text mode renders the overlay with PHP's GD extension
     * (standard in every WordPress environment), uploads it, and returns a type=image
     * element identical in structure to image mode.
     *
     * IMAGE mode: the configured overlay image is uploaded as bytes via addFile() when it
     * resolves to a local file (fix for *.local/LocalWP where addFileFromUrl is blocked),
     * with addFileFromUrl() as a fallback for external public URLs.
     *
     * TEXT mode: the text is rasterised to a transparent PNG by render_text_watermark(),
     * uploaded as bytes, and the temp PNG is deleted immediately after upload.
     *
     * @param array              $options The decoded 'iloveimg_options_watermark' option.
     * @param WatermarkImageTask $my_task The task the element belongs to.
     *
     * @throws \RuntimeException When image mode is selected without a usable image URL.
     *
     * @return array The element parameters (always type=image).
     *
     * @since 3.0.0
     */
    private function get_element_params( $options, $my_task ) {

        // Build the common geometry — always present on a type=image element.
        $params = array(
            'type'                          => 'image',
            'width_percent'                 => $this->get_int_option( $options, 'iloveimg_field_scale', 33, 1, 100 ),
            'transparency'                  => $this->get_int_option( $options, 'iloveimg_field_opacity', 100, 1, 100 ),
            'rotation'                      => $this->get_int_option( $options, 'iloveimg_field_rotation', 0, 0, 359 ),
            'gravity'                       => $this->get_gravity( $options ),
            'mosaic'                        => ! empty( $options['iloveimg_field_mosaic'] ),
            'vertical_adjustment_percent'   => 2,
            'horizontal_adjustment_percent' => 2,
        );

        $wm_type = isset( $options['iloveimg_field_type'] ) ? (string) $options['iloveimg_field_type'] : '';

        if ( 'text' !== $wm_type ) {
            // IMAGE mode: validate the overlay URL, then upload as bytes if local,
            // or fall back to addFileFromUrl for external/public URLs.
            $image_url = isset( $options['iloveimg_field_image'] ) ? trim( (string) $options['iloveimg_field_image'] ) : '';

            // The API fetches this URL, not WordPress, so validate its shape rather than
            // resolving the host: wp_http_validate_url() would reject valid remote images.
            $has_valid_scheme = in_array( wp_parse_url( $image_url, PHP_URL_SCHEME ), array( 'http', 'https' ), true );

            if ( '' === $image_url || ! filter_var( $image_url, FILTER_VALIDATE_URL ) || ! $has_valid_scheme ) {
                throw new \RuntimeException( esc_html__( 'Select a valid watermark image in the plugin settings.', 'iloveimg-watermark' ) );
            }

            // REQ-2: Upload as bytes when the URL resolves to a local file (fixes *.local).
            $local_path = $this->resolve_source_path( $image_url );
            if ( $this->is_valid_source_file( $local_path ) ) {
                $watermark = $my_task->addFile( $local_path );
            } else {
                $watermark = $my_task->addFileFromUrl( $image_url );
            }

            $params['server_filename'] = $watermark->getServerFilename();

            return $params;
        }

        // TEXT mode: rasterise to a transparent PNG and upload as bytes.
        // The API's native type=text element returns DamagedFile for every request
        // (see openspec/changes/fix-watermark-php83-processing/watermark-api-finding.md).
        $png  = $this->render_text_watermark( $options );
        $file = null;
        try {
            $file = $my_task->addFile( $png );
        } finally {
            wp_delete_file( $png ); // Clean up temp PNG even if addFile() throws.
        }

        $params['server_filename'] = $file->getServerFilename();

        return $params;
    }

    /**
     * Rasterise the configured text to a transparent PNG and return the temp file path.
     *
     * WHY this method exists:
     * The iLoveIMG developer API's `watermarkimage` tool returns `DamagedFile` for every
     * `type=text` element (proven — see
     * openspec/changes/fix-watermark-php83-processing/watermark-api-finding.md). Instead
     * we render the text with PHP's GD extension (standard in every WordPress environment)
     * and submit a `type=image` element, exactly as iloveimg.com's own watermark tool does.
     *
     * The PNG is rendered at size 72 for crispness; the API scales the overlay to the
     * configured width_percent so the final watermark size is still controlled by settings.
     * The background is fully transparent so the overlay composites cleanly over the base
     * image. If FreeType/TTF is unavailable the renderer falls back to GD's built-in
     * bitmapped font so text always renders without a fatal.
     *
     * The caller is responsible for deleting the returned temp file after addFile().
     *
     * @param array $options The decoded 'iloveimg_options_watermark' option.
     *
     * @throws \RuntimeException When the GD extension is entirely absent.
     *
     * @return string Absolute path to the rendered temp PNG.
     *
     * @since 3.0.0
     */
    private function render_text_watermark( $options ): string {

        if ( ! function_exists( 'imagecreatetruecolor' ) ) {
            throw new \RuntimeException( esc_html__( 'Image library (GD) is required to render text watermarks.', 'iloveimg-watermark' ) );
        }

        // Extract and validate text settings.
        $text      = isset( $options['iloveimg_field_text'] ) ? trim( (string) $options['iloveimg_field_text'] ) : '';
        $text      = '' !== $text ? $text : 'Sample';
        $text      = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 100 ) : substr( $text, 0, 100 );
        $color_raw = isset( $options['iloveimg_field_text_color'] ) ? trim( (string) $options['iloveimg_field_text_color'] ) : '';
        $color_hex = preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color_raw ) ? $color_raw : '#000000';
        $family    = isset( $options['iloveimg_field_text_family'] ) ? (string) $options['iloveimg_field_text_family'] : '';
        $bold      = ! empty( $options['iloveimg_field_text_bold'] );
        $italic    = ! empty( $options['iloveimg_field_text_italic'] );
        $size      = 72; // Render crisp at 72 pt; the API scales via width_percent.

        // Resolve a usable TTF font (cascade: bundled → system → '').
        $font_file = $this->get_font_file( $family, $bold, $italic );
        $use_ttf   = '' !== $font_file && function_exists( 'imagettfbbox' ) && function_exists( 'imagettftext' );

        // Parse hex color to RGB components.
        $hex_clean = ltrim( $color_hex, '#' );
        if ( 3 === strlen( $hex_clean ) ) {
            $hex_clean = $hex_clean[0] . $hex_clean[0] . $hex_clean[1] . $hex_clean[1] . $hex_clean[2] . $hex_clean[2];
        }
        $r = (int) hexdec( substr( $hex_clean, 0, 2 ) );
        $g = (int) hexdec( substr( $hex_clean, 2, 2 ) );
        $b = (int) hexdec( substr( $hex_clean, 4, 2 ) );

        // Determine canvas dimensions based on font availability.
        $padding = 10;
        $gd_font = 5; // Largest GD built-in font (9×15 px per char), used as fallback.

        if ( $use_ttf ) {
            $bbox = imagettfbbox( $size, 0, $font_file, $text );
            if ( false !== $bbox ) {
                // imagettfbbox at angle 0 returns eight coordinates (four corners).
                // Indices 4 and 5 are the upper-right corner; 0 and 1 are the lower-left.
                // The y-axis is positive downward, so height is the absolute difference of y values.
                $text_width  = (int) abs( $bbox[4] - $bbox[0] );
                $text_height = (int) abs( $bbox[5] - $bbox[1] );
                $baseline_x  = $padding - (int) $bbox[6];
                $baseline_y  = $padding + $text_height - (int) abs( $bbox[1] );
            } else {
                // imagettfbbox failed unexpectedly; fall back to built-in font sizing.
                $use_ttf     = false;
                $text_width  = imagefontwidth( $gd_font ) * strlen( $text );
                $text_height = imagefontheight( $gd_font );
                $baseline_x  = $padding;
                $baseline_y  = $padding;
            }
        } else {
            // GD built-in font: 9×15 px per character.
            $text_width  = imagefontwidth( $gd_font ) * strlen( $text );
            $text_height = imagefontheight( $gd_font );
            $baseline_x  = $padding;
            $baseline_y  = $padding;
        }

        $canvas_w = $text_width + $padding * 2;
        $canvas_h = $text_height + $padding * 2;

        // Create a true-colour image with a fully transparent background.
        $img = imagecreatetruecolor( $canvas_w, $canvas_h );
        imagealphablending( $img, false );  // Disable blending so the fill stays transparent.
        imagesavealpha( $img, true );       // Preserve alpha channel in the saved PNG.
        $transparent = imagecolorallocatealpha( $img, 0, 0, 0, 127 ); // 127 = fully transparent.
        imagefill( $img, 0, 0, $transparent );

        $ink = imagecolorallocate( $img, $r, $g, $b );

        if ( $use_ttf ) {
            imagettftext( $img, $size, 0, $baseline_x, $baseline_y, $ink, $font_file, $text );
        } else {
            // Fallback: GD built-in bitmapped font — no TTF/FreeType required.
            // The API scales the overlay to width_percent, so the smaller pixel size is acceptable.
            imagestring( $img, $gd_font, $baseline_x, $baseline_y, $text, $ink );
        }

        // Write to a temp file with a .png suffix.
        $tmp_dir  = get_temp_dir();
        $tmp_base = wp_tempnam( 'iloveimg-wm', $tmp_dir );
        $png_path = $tmp_base . '.png';

        // wp_tempnam creates a placeholder at $tmp_base; clean it up and write PNG at the .png path.
        wp_delete_file( $tmp_base );
        if ( false === imagepng( $img, $png_path ) ) {
            imagedestroy( $img );
            throw new \RuntimeException( esc_html__( 'Could not write the temporary watermark image.', 'iloveimg-watermark' ) );
        }
        imagedestroy( $img );

        return $png_path;
    }

    /**
     * Resolve a usable TTF font file path, or return '' to signal the GD built-in fallback.
     *
     * The resolution cascade is:
     *   1. BUNDLED  — ILOVE_IMG_WM_FONTS_DIR (admin/fonts/). DejaVu TTFs are added separately;
     *                 see admin/fonts/README.md for how to add or replace them.
     *   2. SYSTEM   — common known TTF paths on Linux (DejaVu/Liberation), Windows (arial/
     *                 times/cour variants), and macOS (/Library/Fonts, /System/Library/Fonts).
     *   3. ''       — no TTF found; the caller renders with GD's built-in bitmapped font.
     *
     * Family mapping (UI name → base family used for file lookup):
     *   Times New Roman → serif | Courier → mono | everything else → sans.
     *
     * Style mapping (bold/italic flags → variant suffix):
     *   bold+italic → BoldItalic | bold → Bold | italic → Italic | neither → Regular.
     *
     * Only paths that actually file_exists() are returned.
     *
     * @param string $family The font family name from the settings (e.g. 'Arial', 'Courier').
     * @param bool   $bold   Whether bold style is requested.
     * @param bool   $italic Whether italic style is requested.
     *
     * @return string Absolute path to a usable .ttf file, or '' when none is found.
     *
     * @since 3.0.0
     */
    private function get_font_file( $family, $bold, $italic ): string {

        // Map the UI family name to the base family we have font files for.
        if ( 'Times New Roman' === $family ) {
            $base = 'serif';
        } elseif ( 'Courier' === $family ) {
            $base = 'mono';
        } else {
            $base = 'sans';
        }

        // Map bold/italic flags to a style variant name.
        if ( $bold && $italic ) {
            $style = 'BoldItalic';
        } elseif ( $bold ) {
            $style = 'Bold';
        } elseif ( $italic ) {
            $style = 'Italic';
        } else {
            $style = 'Regular';
        }

        // 1. Bundled DejaVu TTFs (see admin/fonts/README.md for how to add them).
        $bundled_map = array(
            'sans'  => array(
                'Regular'    => 'DejaVuSans.ttf',
                'Bold'       => 'DejaVuSans-Bold.ttf',
                'Italic'     => 'DejaVuSans-Oblique.ttf',
                'BoldItalic' => 'DejaVuSans-BoldOblique.ttf',
            ),
            'serif' => array(
                'Regular'    => 'DejaVuSerif.ttf',
                'Bold'       => 'DejaVuSerif-Bold.ttf',
                'Italic'     => 'DejaVuSerif.ttf',         // No italic variant; fall back to regular.
                'BoldItalic' => 'DejaVuSerif-Bold.ttf',    // No bold-italic; fall back to bold.
            ),
            'mono'  => array(
                'Regular'    => 'DejaVuSansMono.ttf',
                'Bold'       => 'DejaVuSansMono-Bold.ttf',
                'Italic'     => 'DejaVuSansMono.ttf',      // No italic variant; fall back to regular.
                'BoldItalic' => 'DejaVuSansMono-Bold.ttf', // No bold-italic; fall back to bold.
            ),
        );

        if ( defined( 'ILOVE_IMG_WM_FONTS_DIR' ) ) {
            $candidate = ILOVE_IMG_WM_FONTS_DIR . $bundled_map[ $base ][ $style ];
            if ( file_exists( $candidate ) ) {
                return $candidate;
            }
        }

        // 2. System font paths — probe common locations on each platform.
        $system_candidates = array();

        if ( 'Linux' === PHP_OS_FAMILY ) {
            $linux_map         = array(
                'sans'  => array(
                    'Regular'    => array(
                        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
                    ),
                    'Bold'       => array(
                        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
                    ),
                    'Italic'     => array(
                        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Oblique.ttf',
                        '/usr/share/fonts/truetype/liberation/LiberationSans-Italic.ttf',
                    ),
                    'BoldItalic' => array(
                        '/usr/share/fonts/truetype/dejavu/DejaVuSans-BoldOblique.ttf',
                        '/usr/share/fonts/truetype/liberation/LiberationSans-BoldItalic.ttf',
                    ),
                ),
                'serif' => array(
                    'Regular'    => array(
                        '/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf',
                        '/usr/share/fonts/truetype/liberation/LiberationSerif-Regular.ttf',
                    ),
                    'Bold'       => array(
                        '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf',
                        '/usr/share/fonts/truetype/liberation/LiberationSerif-Bold.ttf',
                    ),
                    'Italic'     => array(
                        '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Italic.ttf',
                        '/usr/share/fonts/truetype/liberation/LiberationSerif-Italic.ttf',
                    ),
                    'BoldItalic' => array(
                        '/usr/share/fonts/truetype/dejavu/DejaVuSerif-BoldItalic.ttf',
                        '/usr/share/fonts/truetype/liberation/LiberationSerif-BoldItalic.ttf',
                    ),
                ),
                'mono'  => array(
                    'Regular'    => array(
                        '/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf',
                        '/usr/share/fonts/truetype/liberation/LiberationMono-Regular.ttf',
                    ),
                    'Bold'       => array(
                        '/usr/share/fonts/truetype/dejavu/DejaVuSansMono-Bold.ttf',
                        '/usr/share/fonts/truetype/liberation/LiberationMono-Bold.ttf',
                    ),
                    'Italic'     => array(
                        '/usr/share/fonts/truetype/dejavu/DejaVuSansMono-Oblique.ttf',
                        '/usr/share/fonts/truetype/liberation/LiberationMono-Italic.ttf',
                    ),
                    'BoldItalic' => array(
                        '/usr/share/fonts/truetype/dejavu/DejaVuSansMono-BoldOblique.ttf',
                        '/usr/share/fonts/truetype/liberation/LiberationMono-BoldItalic.ttf',
                    ),
                ),
            );
            $system_candidates = $linux_map[ $base ][ $style ];
        } elseif ( 'Windows' === PHP_OS_FAMILY ) {
            $win_map             = array(
                'sans'  => array(
                    'Regular'    => 'C:/Windows/Fonts/arial.ttf',
                    'Bold'       => 'C:/Windows/Fonts/arialbd.ttf',
                    'Italic'     => 'C:/Windows/Fonts/ariali.ttf',
                    'BoldItalic' => 'C:/Windows/Fonts/arialbi.ttf',
                ),
                'serif' => array(
                    'Regular'    => 'C:/Windows/Fonts/times.ttf',
                    'Bold'       => 'C:/Windows/Fonts/timesbd.ttf',
                    'Italic'     => 'C:/Windows/Fonts/timesi.ttf',
                    'BoldItalic' => 'C:/Windows/Fonts/timesbi.ttf',
                ),
                'mono'  => array(
                    'Regular'    => 'C:/Windows/Fonts/cour.ttf',
                    'Bold'       => 'C:/Windows/Fonts/courbd.ttf',
                    'Italic'     => 'C:/Windows/Fonts/couri.ttf',
                    'BoldItalic' => 'C:/Windows/Fonts/courbi.ttf',
                ),
            );
            $system_candidates[] = $win_map[ $base ][ $style ];
        } elseif ( 'Darwin' === PHP_OS_FAMILY ) {
            // macOS: common font locations for sans/serif/mono.
            // Bold and italic variants are not mapped here; GD will render them unstyled using the regular face.
            if ( 'sans' === $base ) {
                $system_candidates = array(
                    '/Library/Fonts/Arial.ttf',
                    '/System/Library/Fonts/Supplemental/Arial.ttf',
                );
            } elseif ( 'serif' === $base ) {
                $system_candidates = array(
                    '/Library/Fonts/Times New Roman.ttf',
                    '/System/Library/Fonts/Supplemental/Times New Roman.ttf',
                );
            } else {
                $system_candidates = array(
                    '/Library/Fonts/Courier New.ttf',
                    '/System/Library/Fonts/Supplemental/Courier New.ttf',
                );
            }
        }

        foreach ( $system_candidates as $candidate ) {
            if ( file_exists( $candidate ) ) {
                return $candidate;
            }
        }

        // 3. No TTF found — signal the caller to use GD's built-in bitmapped font.
        return '';
    }

    /**
     * Read a numeric setting, clamped to the range the API accepts.
     *
     * @param array  $options       The decoded settings.
     * @param string $key           The setting to read.
     * @param int    $default_value Returned when the setting is missing, empty or not numeric.
     * @param int    $min           The lowest accepted value.
     * @param int    $max           The highest accepted value.
     *
     * @return int The validated value.
     *
     * @since 3.0.0
     */
    private function get_int_option( $options, $key, $default_value, $min, $max ) {

        if ( ! isset( $options[ $key ] ) || '' === $options[ $key ] || ! is_numeric( $options[ $key ] ) ) {
            return $default_value;
        }

        return max( $min, min( $max, (int) $options[ $key ] ) );
    }

    /**
     * Translate the stored position into a gravity value the API accepts.
     *
     * @param array $options The decoded settings.
     *
     * @return string The gravity value.
     *
     * @since 3.0.0
     */
    private function get_gravity( $options ) {

        $index = isset( $options['iloveimg_field_position'] ) ? (int) $options['iloveimg_field_position'] - 1 : -1;

        return isset( self::$gravity_map[ $index ] ) ? self::$gravity_map[ $index ] : 'Center';
    }

    /**
     * Turn a failure into a message that says what actually went wrong.
     *
     * Raw API error tokens and per-file getErrors() detail are written to error_log only;
     * the user-facing message is always a neutral, actionable string (REQ-E1/E3).
     *
     * @param \Throwable $e The failure.
     *
     * @return string The message shown to the user.
     *
     * @since 3.0.0
     */
    private function get_error_message( \Throwable $e ): string {

        $api_details = '';

        if ( $e instanceof ExtendedException ) {
            foreach ( (array) $e->getErrors() as $error ) {
                if ( is_object( $error ) && isset( $error->error ) ) {
                    $api_details .= ' ' . $error->error;
                }
            }
        }

        error_log( // phpcs:ignore
            sprintf(
                'iLoveIMG watermark error: %s [%s] %s%s',
                get_class( $e ),
                $e->getCode(),
                $e->getMessage(),
                $api_details
            )
        );

        if ( 401 === $e->getCode() ) {
            return __( 'Check your plugin credentials. If you deleted a project in your iLoveAPI account, try switching projects to correctly store your API keys.', 'iloveimg-watermark' );
        }

        if ( $e instanceof \RuntimeException ) {
            return $e->getMessage();
        }

        // REQ-E1/E3: Return a neutral message for API errors; raw tokens stay in error_log only.
        return __( 'This image could not be watermarked. Please try again; if it keeps happening, re-upload the image.', 'iloveimg-watermark' );
    }

    /**
     * Return attachments stuck in the "processing" state to the idle state.
     *
     * A fatal error between marking an attachment as processing and finishing it leaves the
     * status at 1 forever. Because the queue gate counts those rows site-wide and
     * ILOVE_IMG_WM_NUM_MAX_FILES is small, a couple of orphans would otherwise block every
     * later request without ever reaching the API.
     *
     * @return void
     *
     * @since 3.0.0
     */
    private function release_stalled_processes() {

        global $wpdb;

        $stalled = $wpdb->get_col( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'iloveimg_status_watermark' AND meta_value = 1" ); // phpcs:ignore

        foreach ( $stalled as $post_id ) {
            $started = (int) get_post_meta( $post_id, 'iloveimg_watermark_started', true );

            if ( $started && ( time() - $started ) < ILOVE_IMG_WM_PROCESS_TIMEOUT ) {
                continue;
            }

            update_post_meta( $post_id, 'iloveimg_status_watermark', 0 );
            delete_post_meta( $post_id, 'iloveimg_watermark_started' );
        }
    }
}
