<?php
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
        update_option( 'iloveimg_options_watermark', wp_json_encode( $ilove_img_wm_options ) );

        wp_safe_redirect( admin_url( 'admin.php?page=iloveimg-watermark-admin-page' ) );
        exit();
    }

	$ilove_img_wm_account = json_decode( get_option( 'iloveimg_account' ), true );

	$ilove_img_wm_is_logged = true;
	update_option( 'iloveimg_first_loggued', 1 );
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
            update_option( 'iloveimg_account', wp_json_encode( $ilove_img_wm_account ) );
        }
    } else {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><strong>iLoveIMG</strong> - We were unable to verify the status of your iloveAPI account. Please try again later.</p>
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
                <h3>Register as iLoveAPI developer</h3>
                <input type="hidden" name="iloveimg_action" value="iloveimg_action_register" />
                <div>
                    <div style="width: 100%;">
                        <div>
                            <input type="text" class="iloveimg_field_name" name="iloveimg_field_name" placeholder="Name" required value="<?php echo isset( $ilove_img_wm_account_error['name'] ) ? esc_html( $ilove_img_wm_account_error['name'] ) : ''; ?>"/>
                        </div>
                        <div>
                            <input type="email" class="iloveimg_field_email" name="iloveimg_field_email" placeholder="Email" required value="<?php echo isset( $ilove_img_wm_account_error['email'] ) ? esc_attr( $ilove_img_wm_account_error['email'] ) : ''; ?>"/>
                        </div>
                        <div>
                            <input type="password" class="iloveimg_field_password" name="iloveimg_field_password" placeholder="Password" required/>
                        </div>
                    </div>
                    <div>
                        
                        <!-- <div>
                            <input type="password" class="iloveimg_field_password" name="iloveimg_field_password_confirm" placeholder="Confirm Password" required/>
                        </div> -->
                    </div>
                </div>
                <?php
                wp_nonce_field();
                submit_button( 'Register' );
                ?>
                <div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=iloveimg-watermark-admin-page' ) ); ?>">Login to your account</a>
                </div>
            </form>
        </div>
    <?php else : ?>
        <div class="iloveimg_settings__overview__account iloveimg_settings__overview__account-login">
            <!-- <img src="<?php echo esc_url( ILOVE_IMG_WM_PLUGIN_URL . 'assets/images/iloveimg_picture_login.svg' ); ?>" /> -->
            <div class="iloveimg_settings__overview__account__picture"></div>
            <form method="post" action="<?php echo esc_html( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
                <input type="hidden" name="action" value="update_watermark" />
                <h3>Login to your account</h3>
                <input type="hidden" name="iloveimg_action" value="iloveimg_action_login" />
                <div>
                    <input type="email" class="iloveimg_field_email" name="iloveimg_field_email" placeholder="Email" required value="<?php echo isset( $ilove_img_wm_account_error['email'] ) ? esc_attr( $ilove_img_wm_account_error['email'] ) : ''; ?>" />
                </div>
                <div>
                    <input type="password" class="iloveimg_field_password" name="iloveimg_field_password" placeholder="Password" required/>
                </div>
                <a class="forget" href="https://iloveapi.com/login/reset" target="_blank">Forget Password?</a>
                <?php
                wp_nonce_field();
                submit_button( 'Login' );
                ?>
                <div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=iloveimg-watermark-admin-page&section=register' ) ); ?>">Register as iLoveAPI developer</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
<?php else : ?>
    <div class="iloveimg_settings__overview__account iloveimg_settings__overview__account-logged">
        <div class="iloveimg_settings__overview__account-logged__column_left">
            <div class="iloveimg_settings__overview__account-logged__column_left__stadistics">
                <h4 style="color: #4D90FE">Free</h4>
                <?php $ilove_img_wm_percent = ( ( ( $ilove_img_wm_account['files_used'] * 100 ) / $ilove_img_wm_account['free_files_limit'] ) ); ?>
                <div class="iloveimg_percent <?php echo ( $ilove_img_wm_percent >= 100 ) ? 'iloveimg_percent-exceeded' : ''; ?> <?php echo ( $ilove_img_wm_percent >= 90 && $ilove_img_wm_percent < 100 ) ? 'iloveimg_percent-warning' : ''; ?>">
                    <div class="iloveimg_percent-total" style="width: <?php echo (float) $ilove_img_wm_percent; ?>%;"></div>
                </div>
                <p><?php echo (int) $ilove_img_wm_account['files_used']; ?>/<?php echo (int) $ilove_img_wm_account['free_files_limit']; ?> credits used this month. Free Tier.</p>
                <?php if ( $ilove_img_wm_account['subscription_files_limit'] ) : ?>
                    <h4>Subscription files</h4>
                    <?php $ilove_img_wm_percent = ( ( $ilove_img_wm_account['subscription_files_used'] * 100 ) / $ilove_img_wm_account['subscription_files_limit'] ); ?>
                    <div class="iloveimg_percent <?php echo ( $ilove_img_wm_percent >= 100 ) ? 'iloveimg_percent-exceeded' : ''; ?> <?php echo ( $ilove_img_wm_percent >= 90 && $ilove_img_wm_percent < 100 ) ? 'iloveimg_percent-warning' : ''; ?>">
                        <div class="iloveimg_percent-total" style="width: <?php echo (float) $ilove_img_wm_percent; ?>%;"></div>
                    </div>
                    <p><?php echo ( isset( $ilove_img_wm_account['subscription_files_used'] ) ) ? (int) $ilove_img_wm_account['subscription_files_used'] : 0; ?>/<?php echo (int) $ilove_img_wm_account['subscription_files_limit']; ?> credits used this month.</p>
                <?php endif; ?>
                <?php if ( $ilove_img_wm_account['package_files_limit'] ) : ?>
                    <h4>Package files</h4>
                    <?php $ilove_img_wm_percent = ( ( $ilove_img_wm_account['package_files_used'] * 100 ) / $ilove_img_wm_account['package_files_limit'] ); ?>
                    <div class="iloveimg_percent <?php echo ( $ilove_img_wm_percent >= 100 ) ? 'iloveimg_percent-exceeded' : ''; ?> <?php echo ( $ilove_img_wm_percent >= 90 && $ilove_img_wm_percent < 100 ) ? 'iloveimg_percent-warning' : ''; ?>">
                        <div class="iloveimg_percent-total" style="width: <?php echo (float) $ilove_img_wm_percent; ?>%;"></div>
                    </div>
                    <p><?php echo (int) $ilove_img_wm_account['package_files_used']; ?>/<?php echo (int) $ilove_img_wm_account['package_files_limit']; ?> credits used this month.</p>
                <?php endif; ?>
            </div>
            <div class="iloveimg_settings__overview__account-logged__column_left__details">
                <p style="margin-top: 22px;">Every month since your registry you will get <?php echo (int) $ilove_img_wm_account['free_files_limit']; ?> free credits to use to compress or stamp your images.</p>
                <p>To increase your credits amount you can either open one of our <a href="https://iloveapi.com/pricing" target="_blank">subscription plans</a> to get a fixed amount of additional credits per month or buy a <a href="https://iloveapi.com/pricing" target="_blank">single package</a> of credits.</p>
                <a class="button button-secondary" href="https://iloveapi.com/pricing" target="_blank">Buy more credits</a>
            </div>
        </div>
        <div class="iloveimg_settings__overview__account-logged__column_right">
            <form method="post" action="<?php echo esc_html( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="update_watermark" />
                <input type="hidden" name="iloveimg_action" value="iloveimg_action_logout" />
                <h3>Account</h3>
                <p style="margin: 0"><?php echo esc_html( $ilove_img_wm_account['name'] ); ?></p>
                <p style="margin-top: 0; color: #4D90FE;"><?php echo esc_attr( $ilove_img_wm_account['email'] ); ?></p>
                
                <?php wp_nonce_field(); ?>
                <?php submit_button( 'Logout' ); ?>
            </form>

            <form class="iloveimg_settings__overview__account-logged__column_right-proyects" method="post" action="<?php echo esc_html( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="update_watermark" />
                <input type="hidden" name="iloveimg_action" value="iloveimg_action_proyect" />
                <p><label>
                    Select your working proyect
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
                    <button type="submit" class="button button-secondary">Save</button>
                </p>
                <?php wp_nonce_field(); ?>
                
            </form>
        </div>
    </div>
<?php endif; ?>