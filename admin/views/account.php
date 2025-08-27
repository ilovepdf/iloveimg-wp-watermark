<?php
use Ilove_Img_Wm\Ilove_Img_Wm_Resources;

$ilove_img_wm_is_logged = false;
$ilove_img_wm_account   = array();

if ( get_option( 'iloveimg_account' ) ) {

    if ( ! get_option( 'iloveimg_user_is_migrated' ) ) {

        delete_option( 'iloveimg_account' );
        delete_option( 'iloveimg_proyect' );
        $ilove_img_wm_options = json_decode( get_option( 'iloveimg_options_watermark' ), true );
        unset( $options['iloveimg_field_watermark_activated'] );
        unset( $options['iloveimg_field_autowatermark'] );
        unset( $options['iloveimg_field_resize_full'] );
        Ilove_Img_Wm_Resources::update_option( 'iloveimg_options_watermark', wp_json_encode( $ilove_img_wm_options ) );

        wp_safe_redirect( admin_url( 'admin.php?page=iloveimg-watermark-admin-page' ) );
        exit();
    }

	$ilove_img_wm_account = json_decode( get_option( 'iloveimg_account' ), true );

	$ilove_img_wm_is_logged = true;
    Ilove_Img_Wm_Resources::update_option( 'iloveimg_first_loggued', 1 );
	$ilove_img_wm_token = $ilove_img_wm_account['token'];

	$ilove_img_wm_response = wp_remote_get(
        ILOVE_IMG_WM_USER_URL . '/' . $ilove_img_wm_account['id'],
		array(
			'headers' => array( 'Authorization' => 'Bearer ' . $ilove_img_wm_token ),
		)
	);

    if ( ! is_wp_error( $ilove_img_wm_response ) ) {
        if ( 200 === $ilove_img_wm_response['response']['code'] ) {
            $ilove_img_wm_account          = json_decode( $ilove_img_wm_response['body'], true );
            $ilove_img_wm_account['token'] = $ilove_img_wm_token;
            Ilove_Img_Wm_Resources::update_option( 'iloveimg_account', wp_json_encode( $ilove_img_wm_account ) );
        }
    } else {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><strong>iLoveIMG</strong> - <?php esc_html_e( 'We were unable to verify the status of your iloveAPI account. Please try again later.', 'iloveimg-watermark' ); ?></p>
        </div>
        <?php
    }
} elseif ( get_option( 'iloveimg_account_error' ) ) {
    $ilove_img_wm_account_error = json_decode( get_option( 'iloveimg_account_error' ), true );
    delete_option( 'iloveimg_account_error' );
}
?>
<?php if ( ! $ilove_img_wm_is_logged ) : ?> 
    <?php if ( isset( $_GET['section'] ) && 'register' === sanitize_text_field( wp_unslash( $_GET['section'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
        <div class="iloveimg_settings__overview__account iloveimg_settings__overview__account-register">
            <div class="iloveimg_settings__overview__account__picture"></div>
            <form method="post" action="<?php echo esc_html( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
                <input type="hidden" name="action" value="update_watermark" />
                <h3><?php echo esc_html_x( 'Register as iLoveAPI developer', 'subtitle', 'iloveimg-watermark' ); ?></h3>
                <input type="hidden" name="iloveimg_action" value="iloveimg_action_register" />
                <div>
                    <div style="width: 100%;">
                        <div>
                            <input type="text" class="iloveimg_field_name" name="iloveimg_field_name" placeholder="<?php echo esc_html_x( 'Name', 'input text', 'iloveimg-watermark' ); ?>" required value="<?php echo isset( $ilove_img_wm_account_error['name'] ) ? esc_html( $ilove_img_wm_account_error['name'] ) : ''; ?>"/>
                        </div>
                        <div>
                            <input type="email" class="iloveimg_field_email" name="iloveimg_field_email" placeholder="<?php echo esc_html_x( 'Email', 'input email', 'iloveimg-watermark' ); ?>" required value="<?php echo isset( $ilove_img_wm_account_error['email'] ) ? esc_attr( $ilove_img_wm_account_error['email'] ) : ''; ?>"/>
                        </div>
                        <div>
                            <input type="password" class="iloveimg_field_password" name="iloveimg_field_password" placeholder="<?php echo esc_html_x( 'Password', 'input password', 'iloveimg-watermark' ); ?>" required/>
                        </div>
                    </div>
                </div>
                <?php
                wp_nonce_field();
                submit_button( _x( 'Register', 'button', 'iloveimg-watermark' ) );
                ?>
                <div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=iloveimg-watermark-admin-page' ) ); ?>">
                        <?php echo esc_html_x( 'Login to your account', 'button', 'iloveimg-watermark' ); ?>
                    </a>
                </div>
            </form>
        </div>
    <?php else : ?>
        <div class="iloveimg_settings__overview__account iloveimg_settings__overview__account-login">
            <!-- <img src="<?php echo esc_url( ILOVE_IMG_WM_PLUGIN_URL . 'assets/images/iloveimg_picture_login.svg' ); ?>" /> -->
            <div class="iloveimg_settings__overview__account__picture"></div>
            <form method="post" action="<?php echo esc_html( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
                <input type="hidden" name="action" value="update_watermark" />
                <h3><?php echo esc_html_x( 'Login to your account', 'subtitle', 'iloveimg-watermark' ); ?></h3>
                <input type="hidden" name="iloveimg_action" value="iloveimg_action_login" />
                <div>
                    <input type="email" class="iloveimg_field_email" name="iloveimg_field_email" placeholder="<?php echo esc_html_x( 'Email', 'input email', 'iloveimg-watermark' ); ?>" required value="<?php echo isset( $ilove_img_wm_account_error['email'] ) ? esc_attr( $ilove_img_wm_account_error['email'] ) : ''; ?>" />
                </div>
                <div>
                    <input type="password" class="iloveimg_field_password" name="iloveimg_field_password" placeholder="<?php echo esc_html_x( 'Password', 'input password', 'iloveimg-watermark' ); ?>" required/>
                </div>
                <a class="forget" href="https://iloveapi.com/login/reset" target="_blank">
                    <?php echo esc_html_x( 'Forget Password?', 'button', 'iloveimg-watermark' ); ?>
                </a>
                <?php
                wp_nonce_field();
                submit_button( _x( 'Login', 'button', 'iloveimg-watermark' ) );
                ?>
                <div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=iloveimg-watermark-admin-page&section=register' ) ); ?>">
                        <?php echo esc_html_x( 'Register as iLoveAPI developer', 'button', 'iloveimg-watermark' ); ?>
                    </a>
                </div>
            </form>
        </div>
    <?php endif; ?>
<?php else : ?>
    <div class="iloveimg_settings__overview__account iloveimg_settings__overview__account-logged">
        <div class="iloveimg_settings__overview__account-logged__column_left">
            <div class="iloveimg_settings__overview__account-logged__column_left__stadistics">
                <h4 style="color: #4D90FE">
                    <?php echo esc_html_x( 'Free', 'subtitle section: free credits', 'iloveimg-watermark' ); ?>
                </h4>
                <?php
                $ilove_img_wm_percent = ( ( ( $ilove_img_wm_account['files_used'] * 100 ) / $ilove_img_wm_account['free_files_limit'] ) );
                ?>
                <div class="iloveimg_percent <?php echo ( $ilove_img_wm_percent >= 100 ) ? 'iloveimg_percent-exceeded' : ''; ?> <?php echo ( $ilove_img_wm_percent >= 90 && $ilove_img_wm_percent < 100 ) ? 'iloveimg_percent-warning' : ''; ?>">
                    <div class="iloveimg_percent-total" style="width: <?php echo (float) $ilove_img_wm_percent; ?>%;"></div>
                </div>
                <p>
                    <?php
                        printf(
                            /* translators: %1$d: credits used / %2$d: credits limit */
                            esc_html__( '%1$d / %2$d credits used this month. Free Tier.', 'iloveimg-watermark' ),
                            (int) $ilove_img_wm_account['files_used'],
                            (int) $ilove_img_wm_account['free_files_limit']
                        );
                    ?>
                </p>
                <?php if ( $ilove_img_wm_account['subscription_files_limit'] ) : ?>
                    <h4>
                        <?php echo esc_html_x( 'Subscription plan', 'subtitle section', 'iloveimg-watermark' ); ?>
                    </h4>
                    <?php
                    $ilove_img_wm_percent = ( ( $ilove_img_wm_account['subscription_files_used'] * 100 ) / $ilove_img_wm_account['subscription_files_limit'] );
                    ?>
                    <div class="iloveimg_percent <?php echo ( $ilove_img_wm_percent >= 100 ) ? 'iloveimg_percent-exceeded' : ''; ?> <?php echo ( $ilove_img_wm_percent >= 90 && $ilove_img_wm_percent < 100 ) ? 'iloveimg_percent-warning' : ''; ?>">
                        <div class="iloveimg_percent-total" style="width: <?php echo (float) $ilove_img_wm_percent; ?>%;"></div>
                    </div>
                    <p>
                        <?php
                        printf(
                            /* translators: %1$d: credits used / %2$d: credits limit */
                            esc_html__( '%1$d / %2$d credits used this month. Subscription plan.', 'iloveimg-watermark' ),
                            (int) $ilove_img_wm_account['subscription_files_used'],
                            (int) $ilove_img_wm_account['subscription_files_limit']
                        );
                        ?>
                    </p>
                <?php endif; ?>

                <?php if ( $ilove_img_wm_account['package_files_limit'] ) : ?>
                    <h4>
                        <?php echo esc_html_x( 'Prepaid packages', 'subtitle section', 'iloveimg-watermark' ); ?>
                    </h4>
                    <?php
                    $ilove_img_wm_percent = ( ( $ilove_img_wm_account['package_files_used'] * 100 ) / $ilove_img_wm_account['package_files_limit'] );
                    ?>
                    <div class="iloveimg_percent <?php echo ( $ilove_img_wm_percent >= 100 ) ? 'iloveimg_percent-exceeded' : ''; ?> <?php echo ( $ilove_img_wm_percent >= 90 && $ilove_img_wm_percent < 100 ) ? 'iloveimg_percent-warning' : ''; ?>">
                        <div class="iloveimg_percent-total" style="width: <?php echo (float) $ilove_img_wm_percent; ?>%;"></div>
                    </div>
                    <p>
                        <?php
                        printf(
                            /* translators: %1$d: credits used / %2$d: credits limit */
                            esc_html__( '%1$d / %2$d credits used this month. Prepaid packages.', 'iloveimg-watermark' ),
                            (int) $ilove_img_wm_account['package_files_used'],
                            (int) $ilove_img_wm_account['package_files_limit']
                        );
                        ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="iloveimg_settings__overview__account-logged__column_left__details">
                <p style="margin-top: 22px;">
                    <?php
                    printf(
                        /* translators: %d: free credits*/
                        esc_html__( 'Every month since your registry you will get %d free credits to use to compress or stamp your images.', 'iloveimg-watermark' ),
                        (int) $ilove_img_wm_account['free_files_limit']
                    );
                    ?>
                </p>
                <p>
                    <?php
                    $ilove_img_wm_subscription_link = sprintf(
                        '<a href="https://iloveapi.com/pricing" target="_blank" rel="noopener noreferrer">%s</a>',
                        _x( 'subscription plans', '1: subscription plan link', 'iloveimg-watermark' )
                    );

                    $ilove_img_wm_single_package_link = sprintf(
                        '<a href="https://iloveapi.com/pricing" target="_blank" rel="noopener noreferrer">%s</a>',
                        _x( 'single package', '2: single package link', 'iloveimg-watermark' )
                    );

                    printf(
                        wp_kses_post(
                            /* translators: %1$s: subscription plan link, %2$s: single package link */
                            __(
                                'To increase your credits amount you can either open one of our %1$s to get a fixed amount of additional credits per month or buy a %2$s of credits.',
                                'iloveimg-watermark'
                            )
                        ),
                        wp_kses_post( $ilove_img_wm_subscription_link ),
                        wp_kses_post( $ilove_img_wm_single_package_link )
                    );
                    ?>
                </p>
                <a class="button button-secondary" href="https://iloveapi.com/pricing" target="_blank">
                    <?php echo esc_html_x( 'Buy more credits', 'button', 'iloveimg-watermark' ); ?>
                </a>
            </div>
        </div>

        <div class="iloveimg_settings__overview__account-logged__column_right">
            <form method="post" action="<?php echo esc_html( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="update_watermark" />
                <input type="hidden" name="iloveimg_action" value="iloveimg_action_logout" />
                <h3><?php echo esc_html_x( 'Account', 'subtitle: Connected user section', 'iloveimg-watermark' ); ?></h3>
                <p style="margin: 0"><?php echo esc_html( $ilove_img_wm_account['name'] ); ?></p>
                <p style="margin-top: 0; color: #4D90FE;"><?php echo esc_attr( $ilove_img_wm_account['email'] ); ?></p>
                
                <?php wp_nonce_field(); ?>
                <?php submit_button( _x( 'Logout', 'button', 'iloveimg-watermark' ) ); ?>
            </form>

            <form class="iloveimg_settings__overview__account-logged__column_right-proyects" method="post" action="<?php echo esc_html( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="update_watermark" />
                <input type="hidden" name="iloveimg_action" value="iloveimg_action_proyect" />
                <p><label>
                    <?php esc_html_e( 'Select your working proyect', 'iloveimg-watermark' ); ?>
                </label>
                    <select name="iloveimg_field_proyect">
                        <?php foreach ( $ilove_img_wm_account['projects'] as $ilove_img_wm_key => $ilove_img_wm_project ) : ?>
                            <option value="<?php echo esc_attr( $ilove_img_wm_project['public_key'] ); ?>#<?php echo esc_attr( $ilove_img_wm_project['secret_key'] ); ?>" 
                                <?php
                                if ( get_option( 'iloveimg_proyect' ) === $ilove_img_wm_project['public_key'] . '#' . $ilove_img_wm_project['secret_key'] ) {
                                    echo 'selected';
                                }
                                ?>
                            ><?php echo esc_html( $ilove_img_wm_project['name'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button button-secondary"><?php echo esc_html_x( 'Save', 'Button: save changes', 'iloveimg-watermark' ); ?></button>
                </p>
                <?php wp_nonce_field(); ?>
                
            </form>
        </div>
    </div>
<?php endif; ?>
