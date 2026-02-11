<?php
use Ilove_Img_Wm\Ilove_Img_Wm_Media_List_Table;

$ilove_img_wm_list_table = new Ilove_Img_Wm_Media_List_Table();
$ilove_img_wm_list_table->prepare_items();

?>
<div class="wrap iloveimg_settings">
	<img src="<?php echo esc_url( ILOVE_IMG_WM_PLUGIN_URL . 'assets/images/logo.svg' ); ?>" class="logo" />
	<div class="iloveimg_settings__overview">
        <?php require_once 'overview.php'; ?>
        <?php if ( $ilove_img_wm_list_table->total_items ) : ?>
            <div class="iloveimg_settings__overview__watermarkAll">
                <button type="button" id="iloveimg_watermarkall" class="iloveimg-watermark-all button button-small button-primary">
                    <span><?php echo esc_html_x( 'Watermark all uploaded files', 'button', 'iloveimg-watermark' ); ?></span>
                    <div class="iloveimg-watermark-all__percent" style="width: 0%;"></div>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="wrap">
        <?php
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Displaying success message from redirect, no action taken
        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local scope variables for view template

        // Display bulk watermark results
        $iloveimg_wm_bulk_status   = isset( $_GET['iloveimg-wm-bulk-watermark'] ) ? sanitize_text_field( wp_unslash( $_GET['iloveimg-wm-bulk-watermark'] ) ) : '';
        $iloveimg_wm_updated_count = isset( $_GET['updated-count'] ) ? intval( wp_unslash( $_GET['updated-count'] ) ) : 0;
        $iloveimg_wm_success_ids   = get_transient( 'iloveimg_wm_bulk_success' );
        $iloveimg_wm_error_items   = get_transient( 'iloveimg_wm_bulk_errors' );

        if ( 'success' === $iloveimg_wm_bulk_status && ! empty( $iloveimg_wm_success_ids ) ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php
                    /* translators: %s: number of images watermarked */
                    echo esc_html( sprintf( _n( '%s image watermarked successfully.', '%s images watermarked successfully.', count( $iloveimg_wm_success_ids ), 'iloveimg-watermark' ), count( $iloveimg_wm_success_ids ) ) );
                    ?>
                </p>
            </div>
            <?php
            delete_transient( 'iloveimg_wm_bulk_success' );
        } elseif ( 'partial' === $iloveimg_wm_bulk_status ) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php
                    /* translators: %1$s: number of watermarked, %2$s: number of failed */
                    echo esc_html( sprintf( _x( '%1$s images watermarked, %2$s images failed.', 'bulk action result', 'iloveimg-watermark' ), count( $iloveimg_wm_success_ids ), count( $iloveimg_wm_error_items ) ) );
                    ?>
                </p>
            </div>
            <?php
            delete_transient( 'iloveimg_wm_bulk_success' );
            delete_transient( 'iloveimg_wm_bulk_errors' );
        } elseif ( 'error' === $iloveimg_wm_bulk_status && ! empty( $iloveimg_wm_error_items ) ) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <?php
                    /* translators: %s: number of images that failed */
                    echo esc_html( sprintf( _n( '%s image failed to watermark.', '%s images failed to watermark.', count( $iloveimg_wm_error_items ), 'iloveimg-watermark' ), count( $iloveimg_wm_error_items ) ) );
                    ?>
                </p>
            </div>
            <?php
            delete_transient( 'iloveimg_wm_bulk_errors' );
        }

        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
        ?>
        <form id="images-filter" method="get">
            <input type="hidden" name="page" value="iloveimg-media-watermark-page" />
            <?php wp_nonce_field( 'iloveimg_wm_bulk_watermark_action', 'iloveimg_wm_bulk_nonce' ); ?>
            <?php $ilove_img_wm_list_table->display(); ?>
        </form>
    </div>
</div>
