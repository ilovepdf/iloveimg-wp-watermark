<?php
namespace Ilove_Img_Wm;

/**
 * Class for handling serialization and management of iLoveIMG plugin settings.
 *
 * This class is responsible for managing the serialization and deserialization of iLoveIMG plugin settings, including options for watermark, login, registration, and project settings. It also handles the validation of nonces and redirects based on user actions.
 *
 * @since 1.0.0
 */
class Ilove_Img_Wm_Serializer {
    /**
     * Initialize the plugin and configure actions related to updating watermarks.
     *
     * This method sets up actions and hooks to handle watermark updates within the WordPress admin area. It specifically uses the 'admin_post_update_watermark' action to trigger the 'save' method when a relevant form is submitted.
     *
     * The 'admin_post_update_watermark' action is typically associated with processing form submissions related to watermark configuration. When this action is triggered, the 'save' method is called to handle the update and save the watermark settings.
     *
     * This method plays a key role in ensuring that watermark updates are handled effectively within the plugin.
     */
    public function init() {
        add_action( 'admin_post_update_watermark', array( $this, 'save' ) );
    }

    /**
     * Handle various actions related to watermark configuration, user authentication, registration, and project settings.
     *
     * This function processes POST requests from forms in the WordPress admin area, specifically those related to watermark configuration, user authentication, registration, and project settings.
     *
     * The function checks for user capabilities, valid nonces, and the specific 'iloveimg_action' field in the submitted form to determine the action to take. The available actions and their functionality are as follows:
     * - 'iloveimg_action_options_watermark': Updates watermark settings.
     * - 'iloveimg_action_logout': Logs the user out and clears account-related settings.
     * - 'iloveimg_action_login': Logs the user in using their email and password.
     * - 'iloveimg_action_register': Registers a new user account.
     * - 'iloveimg_action_proyect': Updates the project setting.
     *
     * Depending on the action, the function updates relevant options or sends API requests to authenticate or register users.
     *
     * This function plays a crucial role in configuring the plugin's settings and handling user interactions within the WordPress admin area.
     */
    public function save() {
        if ( ! ( current_user_can( 'manage_options' ) ) ) {
            die();
        }

        if ( isset( $_POST['iloveimg_action'] ) && $this->has_valid_nonce() ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ( 'iloveimg_action_options_watermark' === $_POST['iloveimg_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing

                $posts_value = array();
                foreach ( $_POST as $key => $post_value ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    if ( strpos( $key, 'iloveimg_field_' ) === 0 ) {
                        $posts_value[ $key ] = wp_unslash( $post_value );
                    }
                }
                update_option( 'iloveimg_options_watermark', wp_json_encode( $posts_value ) );
            }

            if ( 'iloveimg_action_logout' === $_POST['iloveimg_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                delete_option( 'iloveimg_account' );
                delete_option( 'iloveimg_proyect' );
                $options = json_decode( get_option( 'iloveimg_options_watermark' ), true );
                unset( $options['iloveimg_field_watermark_activated'] );
                unset( $options['iloveimg_field_autowatermark'] );
                unset( $options['iloveimg_field_resize_full'] );
                update_option( 'iloveimg_options_watermark', wp_json_encode( $options ) );
            }

            if ( 'iloveimg_action_login' === $_POST['iloveimg_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                if ( ! isset( $_POST['iloveimg_field_email'] ) && ! isset( $_POST['iloveimg_field_password'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    $this->redirect();
                }
                $response = wp_remote_post(
                    ILOVE_IMG_WM_LOGIN_URL,
                    array(
                        'body' => array(
                            'email'        => sanitize_email( wp_unslash( $_POST['iloveimg_field_email'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
                            'password'     => sanitize_text_field( wp_unslash( $_POST['iloveimg_field_password'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
                            'wordpress_id' => md5( get_option( 'siteurl' ) . get_option( 'admin_email' ) ),
                        ),
                    )
                );
                if ( wp_remote_retrieve_response_code( $response ) === 200 ) {
                    update_option( 'iloveimg_account', $response['body'] );
                    $options                                       = json_decode( get_option( 'iloveimg_options_watermark' ), true );
                    $options['iloveimg_field_watermark_activated'] = 1;
                    $options['iloveimg_field_autowatermark']       = 1;
                    update_option( 'iloveimg_options_watermark', wp_json_encode( $options ) );
                } else {
                    update_option(
                        'iloveimg_account_error',
                        wp_json_encode(
                            array(
								'action' => 'login',
								'email'  => sanitize_email( wp_unslash( $_POST['iloveimg_field_email'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
                            )
                        )
                    );
                }
            }

            if ( 'iloveimg_action_register' === $_POST['iloveimg_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                if ( ! isset( $_POST['iloveimg_field_name'] ) && ! isset( $_POST['iloveimg_field_email'] ) && ! isset( $_POST['iloveimg_field_password'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    $this->redirect();
                }
                $response = wp_remote_post(
                    ILOVE_IMG_WM_REGISTER_URL,
                    array(
                        'body' => array(
                            'name'         => sanitize_text_field( wp_unslash( $_POST['iloveimg_field_name'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
                            'email'        => sanitize_email( wp_unslash( $_POST['iloveimg_field_email'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
                            'new_password' => sanitize_text_field( wp_unslash( $_POST['iloveimg_field_password'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
                            'free_files'   => 0,
                            'wordpress_id' => md5( get_option( 'siteurl' ) . get_option( 'admin_email' ) ),
                        ),
                    )
                );
                if ( wp_remote_retrieve_response_code( $response ) === 200 ) {
                    $key = 'iloveimg_number_registered_' . gmdate( 'Ym' );
                    if ( get_option( $key ) ) {
                        $num = (int) get_option( $key );
                        ++$num;
                        update_option( $key, $num );
                    } else {
                        update_option( $key, 1 );
                    }
                    if ( (int) get_option( $key ) <= 3 ) {
                        update_option( 'iloveimg_account', $response['body'] );
                        $options                                       = json_decode( get_option( 'iloveimg_options_watermark' ), true );
                        $options['iloveimg_field_watermark_activated'] = 1;
                        $options['iloveimg_field_autowatermark']       = 1;
                        update_option( 'iloveimg_options_watermark', wp_json_encode( $options ) );
                    } else {
                        update_option( 'iloveimg_account_error', wp_json_encode( array( 'action' => 'register_limit' ) ) );
                    }
                } else {
                    update_option(
                        'iloveimg_account_error',
                        wp_json_encode(
                            array(
                                'action' => 'register',
                                'email'  => sanitize_email( wp_unslash( $_POST['iloveimg_field_email'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
                                'name'   => sanitize_text_field( wp_unslash( $_POST['iloveimg_field_name'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
                            )
                        )
                    );
                }
            }

            if ( 'iloveimg_action_proyect' === $_POST['iloveimg_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                if ( ! isset( $_POST['iloveimg_field_proyect'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    $this->redirect();
                }
                update_option( 'iloveimg_proyect', sanitize_text_field( wp_unslash( $_POST['iloveimg_field_proyect'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            }
		}

        $this->redirect();
	}

    /**
     * Check the validity of a WordPress nonce.
     *
     * This private method checks if a WordPress nonce exists in the request and verifies its validity.
     * A nonce is a security token used to verify the origin of a request, providing protection against CSRF attacks.
     *
     * @return bool Whether the nonce is valid (true) or not (false).
     */
	private function has_valid_nonce() {
        if ( ! isset( $_REQUEST['_wpnonce'] ) ) {
            return false;
        }

        $field = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );

        return wp_verify_nonce( $field );
    }

    /**
     * Perform a safe redirect to a specified URL.
     *
     * This private method is used to redirect to a specified URL after performing various checks
     * to ensure the URL's safety and validity.
     */
	private function redirect() {

        // To make the Coding Standards happy, we have to initialize this.
        if ( ! isset( $_POST['_wp_http_referer'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) ) ) {
            $_POST['_wp_http_referer'] = wp_login_url();
        }

        // Sanitize the value of the $_POST collection for the Coding Standards.
        $url = sanitize_text_field(
            wp_unslash( $_POST['_wp_http_referer'] ) // Input var okay.
        );

        // Finally, redirect back to the admin page.
        wp_safe_redirect( urldecode( $url ) );
        exit;
    }
}
