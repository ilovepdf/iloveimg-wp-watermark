<?php

use Iloveimg\WatermarkImageTask;

class iLoveIMG_Watermark_Process{

    public $proyect_public = '';
    public $secret_key = '';

    

    public function watermark($imagesID){
        global $_wp_additional_image_sizes, $wpdb;

        $images = array();
        try { 

            if(get_option('iloveimg_proyect')){
                $proyect = explode("#", get_option('iloveimg_proyect'));
                $this->proyect_public = $proyect[0];
                $this->secret_key = $proyect[1];
            }else if(get_option('iloveimg_account')){
                $account = json_decode(get_option('iloveimg_account'), true);
                $this->proyect_public = $account['projects'][0]['public_key'];
                $this->secret_key = $account['projects'][0]['secret_key'];
            }

            
            
            $filesProcessing = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = 'iloveimg_status_watermark' AND meta_value = 1" );
            if( $filesProcessing <  iLoveIMG_Watermark_NUM_MAX_FILES){
                update_post_meta($imagesID, 'iloveimg_status_watermark', 1); //status compressing

                $_sizes = get_intermediate_image_sizes();
                
                array_unshift($_sizes,  "full");
                $_aOptions = unserialize(get_option('iloveimg_options_compress'));
                

                foreach ( $_sizes as $_size ) {
                    $image = wp_get_attachment_image_src($imagesID, $_size);
                    $pathFile = $_SERVER["DOCUMENT_ROOT"] . str_replace(site_url(), "", $image[0]);
                    $images[$_size] = array("initial" => filesize($pathFile),  "compressed" => null);
                    if(in_array($_size, $_aOptions['iloveimg_field_sizes'])){
                        
                        $myTask = new WatermarkImageTask($this->proyect_public, $this->secret_key);
                        $file = $myTask->addFile($pathFile);
                        $watermark = $myTask->addFile('/Users/carlos/Documents/Proyectos/WordPress/wp-content/uploads/2019/05/kisspng-digital-watermarking-watercolor-watermark-5ad7f5dc840cc9.0658787515241026205409.jpg');
                        $element = $myTask->addElement([
                           'type' => 'image',
                           'width_percent' => 10,
                           'server_filename' => $watermark->getServerFilename()
                       ]);
                        $myTask->execute();
                        $myTask->download(dirname($pathFile));
                        $images[$_size]["compressed"] = filesize($pathFile);

                        
                    }
                }
                update_post_meta($imagesID, 'iloveimg_watermark', $images);
                update_post_meta($imagesID, 'iloveimg_status_watermark', 2); //status compressed
                return $images;

            }else{
                update_post_meta($imagesID, 'iloveimg_status_watermark', 3); //status queue
                sleep(2);
                return $this->watermark($imagesID);
            }

            //print_r($imagesID);
        } catch (Exception $e)  {
            update_post_meta($imagesID, 'iloveimg_status_watermark', 0);
            //echo $e->getCode();
            return false;
        }
        return false;
    }

}
