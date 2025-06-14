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
                    <span><?php echo esc_html_x( 'Watermark all', 'button', 'iloveimg-watermark' ); ?></span>
                    <div class="iloveimg-watermark-all__percent" style="width: 0%;"></div>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="wrap">
        <form id="images-filter" method="get">
            <?php $ilove_img_wm_list_table->display(); ?>
        </form>
    </div>
</div>