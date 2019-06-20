<?php

class iLoveIMG_Watermark_Plugin {
    const VERSION = '1.0.0';
	const NAME = 'iLoveIMG_Watermark_plugin';
    public function __construct() {
        add_action( 'admin_init', array( $this, "admin_init" ));
    }

    public function admin_init() {
        add_action( 'admin_enqueue_scripts', array($this, "enqueue_scripts"));
        add_filter( 'manage_media_columns', array( $this, "column_id" ) );
        add_filter( 'manage_media_custom_column', array( $this, "column_id_row" ), 10, 2 );
        add_action( 'wp_ajax_iLoveIMG_Watermark_library', array($this, "iLoveIMG_Watermark_library") );
        add_action( 'wp_ajax_iLoveIMG_Watermark_restore', array($this, "iLoveIMG_Watermark_restore") );
        add_action( 'wp_ajax_iLoveIMG_Watermark_clear_backup', array($this, "iLoveIMG_Watermark_clear_backup") );
        add_action( 'wp_ajax_iLoveIMG_Watermark_library_is_watermarked', array($this, "iLoveIMG_Watermark_library_is_watermarked") );
        add_filter( 'wp_generate_attachment_metadata', array($this, 'process_attachment' ), 10, 2);
        add_action( 'admin_action_iloveimg_bulk_action', array($this, "media_library_bulk_action"));
        add_action( 'attachment_submitbox_misc_actions', array($this, 'show_media_info'));
        
        if(!class_exists('iLoveIMG_Library_init')){
            require_once("class-iloveimg-library-init.php");
            new iLoveIMG_Library_init();
        }
        
        add_action( 'admin_notices', array($this, 'show_notices'));
        add_thickbox();
    }

    public function enqueue_scripts(){
        wp_enqueue_script( self::NAME . '_spectrum_admin',
        plugins_url( '/assets/js/spectrum.js', dirname(__FILE__) ),
            array(), self::VERSION, true
        );
        wp_enqueue_script( self::NAME . '_admin',
        plugins_url( '/assets/js/main.js', dirname(__FILE__) ),
			array(), self::VERSION, true
        );
        wp_enqueue_style( self::NAME . '_admin',
        plugins_url( '/assets/css/app.css', dirname(__FILE__) ),
			array(), self::VERSION
		);
    }
    
    public function iLoveIMG_Watermark_library(){
        $ilove = new iLoveIMG_Watermark_Process();
        $images = $ilove->watermark($_POST['id']);
        if($images !== false){
            iLoveIMG_Watermark_Resources::render_compress_details($_POST['id']);
        }else{
            ?>
            <p>You need more files</p>
            <?php
        }
        wp_die();
    }

    public function iLoveIMG_Watermark_restore(){
        if(is_dir(iLoveIMG_upload_folder . "/iloveimg-backup")){   
            $folders = array_diff(scandir(iLoveIMG_upload_folder . "/iloveimg-backup"), array('..', '.'));
            foreach ($folders as $key => $folder) {
                iLoveIMG_Watermark_Resources::rcopy(iLoveIMG_upload_folder . "/iloveimg-backup/" . $folder, iLoveIMG_upload_folder . "/" . $folder);
            }
            iLoveIMG_Watermark_Resources::rrmdir(iLoveIMG_upload_folder . "/iloveimg-backup");
            $images_restore = unserialize(get_option('iloveimg_images_to_restore'));
            foreach ($images_restore as $key => $value) {
                delete_post_meta($value, 'iloveimg_status_watermark');
                delete_post_meta($value, 'iloveimg_watermark');
                delete_option('iloveimg_images_to_restore');
            }
        }

        wp_die();
    }

    public function iLoveIMG_Watermark_clear_backup(){
        if(is_dir(iLoveIMG_upload_folder . "/iloveimg-backup")){   
            iLoveIMG_Watermark_Resources::rrmdir(iLoveIMG_upload_folder . "/iloveimg-backup");
            delete_option('iloveimg_images_to_restore');
        }
        wp_die();
    }
    
    public function iLoveIMG_Watermark_library_is_watermarked(){
        $status_watermark = get_post_meta($_POST['id'], 'iloveimg_status_watermark', true);

        $imagesCompressed = iLoveIMG_Watermark_Resources::getSizesWatermarked($_POST['id']);
        if(((int)$status_watermark === 1 || (int)$status_watermark === 3)){
            //echo "processing";
            http_response_code(404);
            die();
        }else if((int)$status_watermark === 2){
            iLoveIMG_Watermark_Resources::render_compress_details($_POST['id']);
        }
        wp_die();
    }

    public function column_id($columns){
        if((int)iLoveIMG_Watermark_Resources::isActivated() === 0){
            return $columns;
        }
        $columns['iloveimg_status'] = __('Status');
        return $columns;
    }

    public function column_id_row($columnName, $columnID){
        if($columnName == 'iloveimg_status'){
            iLoveIMG_Watermark_Resources::getStatusOfColumn($columnID);
        }
    }

    public function process_attachment($metadata, $attachment_id){
        update_post_meta($attachment_id, 'iloveimg_status_watermark', 0); //status no watermarked
        if((int)iLoveIMG_Watermark_Resources::isAutoCompress() === 1 && iLoveIMG_Watermark_Resources::isLoggued() && (int)iLoveIMG_Watermark_Resources::isActivated() === 1){
            wp_update_attachment_metadata($attachment_id, $metadata);
            $this->async_compress($attachment_id);
        }
        
        return $metadata;
    }

    public function async_compress($attachment_id){
        $args = array(
            'method' => 'POST',
            'timeout' => 0.01,
            'blocking' => false,
            'body' => array( 'action' => 'iLoveIMG_Watermark_library', 'id' => $attachment_id ),
            'cookies'   => isset( $_COOKIE ) && is_array( $_COOKIE ) ? $_COOKIE : array(),
            'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
        );
        if ( getenv( 'WORDPRESS_HOST' ) !== false ) {
			wp_remote_post( getenv( 'WORDPRESS_HOST' ) . '/wp-admin/admin-ajax.php', $args );
		} else {
			wp_remote_post( admin_url( 'admin-ajax.php' ), $args );
		}
    }

    public function media_library_bulk_action(){
        die();
        foreach($_REQUEST['media'] as $attachment_id){
            $post = get_post($attachment_id);
            if(strpos($post->post_mime_type, "image/") !== false){
                $status_watermark = get_post_meta($attachment_id, 'iloveimg_status_watermark', true);
                if((int)$status_watermark === 0){
                    $this->async_compress($attachment_id);
                }
            }
        }
    }

    public function show_notices(){
        if(!iLoveIMG_Watermark_Resources::isLoggued()){
        ?>
            <div class="notice notice-warning is-dismissible">
                <p><strong>iLoveIMG</strong> - Please you need to be logged or registered. <a href="<?php echo admin_url( 'admin.php?page=iloveimg-watermark-admin-page' ) ?>">Go to settings</a></p>
            </div>
            <?php
        }

        if(get_option('iloveimg_account_error')){
                $iloveimg_account_error = unserialize(get_option('iloveimg_account_error'));
            if($iloveimg_account_error['action'] == 'login'):
                ?>
                <div class="notice notice-error is-dismissible">
                    <p>Your email or password is wrong.</p>
                </div>
            <?php endif;  ?>
            <?php if($iloveimg_account_error['action'] == 'register'): ?>
                <div class="notice notice-error is-dismissible">
                    <p>This email address has already been taken.</p>
                </div>
            <?php endif;  ?>
            <?php if($iloveimg_account_error['action'] == 'register_limit'): ?>
                <div class="notice notice-error is-dismissible">
                    <p>You have reached limit of different users to use this Wordpress plugin. Please relogin with one of your existing users.</p>
                </div>
            <?php endif;  ?>
            <?php
            
        }
        //do query
        if(get_option('iloveimg_account')){
            $account = json_decode(get_option('iloveimg_account'), true);
            if(!array_key_exists('error', $account)){
                $token = $account['token'];
                $response = wp_remote_get(iLoveIMG_Watermark_USER_URL.'/'.$account['id'], 
                    array(
                        'headers' => array('Authorization' => 'Bearer '.$token)
                    )
                );

                if (isset($response['response']['code']) && $response['response']['code'] == 200) {
                    $account = json_decode($response["body"], true);
                    if($account['files_used'] >=  $account['free_files_limit'] and $account['package_files_used'] >=  $account['package_files_limit'] and @$account['subscription_files_used'] >=  $account['subscription_files_limit']){
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

    public function show_media_info(){
        global $post;
        echo '<div class="misc-pub-section iloveimg-compress-images">';
        echo '<h4>';
        esc_html_e( 'iLoveIMG', 'iloveimg' );
        echo '</h4>';
        echo '<div class="iloveimg-container">';
        echo '<table><tr><td>';
        $status_watermark = get_post_meta($post->ID, 'iloveimg_status_watermark', true);

        $imagesCompressed = iLoveIMG_Watermark_Resources::getSizesWatermarked($post->ID);
        
        if((int)$status_watermark === 2){
            iLoveIMG_Watermark_Resources::render_compress_details($post->ID);
        }else{
            iLoveIMG_Watermark_Resources::getStatusOfColumn($post->ID);
        }
        echo '</td></tr></table>';
        echo '</div>';
        echo '</div>';
    }

}