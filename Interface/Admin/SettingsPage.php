<?php
/**
 * Plugin Global Settings Page
 *
 * Provides a UI for configuring global plugin options like Payment Gateways.
 *
 * @package Himalayan\Homestay\Interface\Admin
 */

namespace Himalayan\Homestay\Interface\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SettingsPage {

    /**
     * Option key for payment settings.
     */
    const OPTION_KEY = 'hhb_payment_settings';

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_settings_page' ], 30 ); // Load after bookings/calendar
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_post_hhb_save_smtp_config', [ __CLASS__, 'handle_save_smtp_config' ] );
        add_action( 'admin_post_hhb_run_cron',         [ __CLASS__, 'handle_run_cron' ] );
    }

    public static function add_settings_page(): void {
        add_submenu_page(
            'edit.php?post_type=hhb_homestay', // Parent slug (Homestay Bookings)
            __( 'Settings', 'himalayan-homestay-bookings' ),
            __( 'Settings', 'himalayan-homestay-bookings' ),
            'manage_options',
            'hhb-settings',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function register_settings(): void {
        register_setting(
            'hhb_settings_group',
            self::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
                'default'           => [],
            ]
        );

        // Section: Razorpay
        add_settings_section(
            'hhb_section_razorpay',
            __( 'Razorpay Settings', 'himalayan-homestay-bookings' ),
            [ __CLASS__, 'render_section_razorpay' ],
            'hhb-settings'
        );

        // Section: Email Templates
        add_settings_section(
            'hhb_section_email_templates',
            __( 'Email Templates Configuration', 'himalayan-homestay-bookings' ),
            [ __CLASS__, 'render_section_email_templates' ],
            'hhb-settings-emails'
        );

        // Section: Email Templates (Rendered manually in render_page)
        add_settings_section(
            'hhb_section_email_templates',
            __( 'Email Templates Configuration', 'himalayan-homestay-bookings' ),
            [ __CLASS__, 'render_section_email_templates' ],
            'hhb-settings-emails'
        );

        // Section: GDPR & Privacy
        add_settings_section(
            'hhb_section_gdpr',
            __( 'GDPR & Privacy Compliance', 'himalayan-homestay-bookings' ),
            [ __CLASS__, 'render_section_gdpr' ],
            'hhb-settings-gdpr'
        );

        add_settings_field(
            'privacy_policy_url',
            __( 'Privacy Policy URL', 'himalayan-homestay-bookings' ),
            [ __CLASS__, 'render_text_field_gdpr' ],
            'hhb-settings-gdpr',
            'hhb_section_gdpr',
            [
                'key'         => 'privacy_policy_url',
                'description' => __( 'The absolute URL to your Privacy Policy page. Leaving this blank will use the default WordPress Privacy page if set.', 'himalayan-homestay-bookings' ),
            ]
        );

        add_settings_field(
            'razorpay_enabled',
            __( 'Enable Razorpay', 'himalayan-homestay-bookings' ),
            [ __CLASS__, 'render_checkbox_field' ],
            'hhb-settings',
            'hhb_section_razorpay',
            [
                'key'   => 'razorpay_enabled',
                'label' => __( 'Allow customers to securely pay via Razorpay (India & Global).', 'himalayan-homestay-bookings' ),
            ]
        );

        add_settings_field(
            'razorpay_key_id',
            __( 'Key ID', 'himalayan-homestay-bookings' ),
            [ __CLASS__, 'render_text_field' ],
            'hhb-settings',
            'hhb_section_razorpay',
            [
                'key'         => 'razorpay_key_id',
                'description' => __( 'Your Razorpay Key ID.', 'himalayan-homestay-bookings' ),
            ]
        );

        add_settings_field(
            'razorpay_key_secret',
            __( 'Key Secret', 'himalayan-homestay-bookings' ),
            [ __CLASS__, 'render_password_field' ],
            'hhb-settings',
            'hhb_section_razorpay',
            [
                'key'         => 'razorpay_key_secret',
                'description' => __( 'Your Razorpay Key Secret. Keep this safe.', 'himalayan-homestay-bookings' ),
            ]
        );

        add_settings_field(
            'razorpay_webhook_secret',
            __( 'Webhook Secret', 'himalayan-homestay-bookings' ),
            [ __CLASS__, 'render_password_field' ],
            'hhb-settings',
            'hhb_section_razorpay',
            [
                'key'         => 'razorpay_webhook_secret',
                'description' => __( 'Your Razorpay Webhook Secret. Needed to verify payment success signals.', 'himalayan-homestay-bookings' ),
            ]
        );

        // Section: Stripe
        add_settings_section(
            'hhb_section_stripe',
            __( 'Stripe Settings', 'himalayan-homestay-bookings' ),
            [ __CLASS__, 'render_section_stripe' ],
            'hhb-settings'
        );

        add_settings_field(
            'stripe_enabled',
            __( 'Enable Stripe', 'himalayan-homestay-bookings' ),
            [ __CLASS__, 'render_checkbox_field' ],
            'hhb-settings',
            'hhb_section_stripe',
            [
                'key'   => 'stripe_enabled',
                'label' => __( 'Allow customers to securely pay via Stripe (Credit/Debit Cards globally).', 'himalayan-homestay-bookings' ),
            ]
        );

        add_settings_field(
            'stripe_publishable_key',
            __( 'Publishable Key', 'himalayan-homestay-bookings' ),
            [ __CLASS__, 'render_text_field' ],
            'hhb-settings',
            'hhb_section_stripe',
            [
                'key'         => 'stripe_publishable_key',
                'description' => __( 'Your Stripe Publishable API Key.', 'himalayan-homestay-bookings' ),
            ]
        );

        add_settings_field(
            'stripe_secret_key',
            __( 'Secret Key', 'himalayan-homestay-bookings' ),
            [ __CLASS__, 'render_password_field' ],
            'hhb-settings',
            'hhb_section_stripe',
            [
                'key'         => 'stripe_secret_key',
                'description' => __( 'Your Stripe Secret API Key. Keep this safe.', 'himalayan-homestay-bookings' ),
            ]
        );
        // Section: Cash Mode
        add_settings_section(
            'hhb_section_cash_mode',
            __( 'Cash Mode (Pay on Arrival)', 'himalayan-homestay-bookings' ),
            [ __CLASS__, 'render_section_cash_mode' ],
            'hhb-settings'
        );

        add_settings_field(
            'cash_mode_enabled',
            __( 'Enable Cash Mode', 'himalayan-homestay-bookings' ),
            [ __CLASS__, 'render_checkbox_field' ],
            'hhb-settings',
            'hhb_section_cash_mode',
            [
                'key'   => 'cash_mode_enabled',
                'label' => __( 'Allow customers to book instantly and pay when they arrive at the homestay.', 'himalayan-homestay-bookings' ),
            ]
        );
        // Section: Automation Timing
        add_settings_section(
            'hhb_section_automation',
            __( 'Automation Timing', 'himalayan-homestay-bookings' ),
            [ __CLASS__, 'render_section_automation' ],
            'hhb-settings-automation'
        );

        $timing_fields = [
            [
                'key'         => 'payment_expiry_minutes',
                'label'       => __( 'Payment Expiry Window (minutes)', 'himalayan-homestay-bookings' ),
                'description' => __( 'After admin approves a booking, how many minutes does the guest have to pay? Default: 60.', 'himalayan-homestay-bookings' ),
                'default'     => 60,
            ],
            [
                'key'         => 'review_followup_days',
                'label'       => __( 'Review Follow-up Delay (days after checkout)', 'himalayan-homestay-bookings' ),
                'description' => __( 'How many days after checkout to send the second review reminder. Default: 5.', 'himalayan-homestay-bookings' ),
                'default'     => 5,
            ],
            [
                'key'         => 'win_back_primary_days',
                'label'       => __( 'Win-back Primary (days after checkout)', 'himalayan-homestay-bookings' ),
                'description' => __( 'Days after checkout to send the first returning guest offer. Default: 60.', 'himalayan-homestay-bookings' ),
                'default'     => 60,
            ],
            [
                'key'         => 'win_back_secondary_days',
                'label'       => __( 'Win-back Seasonal (days after checkout)', 'himalayan-homestay-bookings' ),
                'description' => __( 'Days after checkout to send the seasonal re-engagement email. Default: 180.', 'himalayan-homestay-bookings' ),
                'default'     => 180,
            ],
        ];

        foreach ( $timing_fields as $field ) {
            add_settings_field(
                $field['key'],
                $field['label'],
                function( $args ) {
                    $opts    = get_option( SettingsPage::OPTION_KEY, [] );
                    $val     = isset( $opts[ $args['key'] ] ) ? intval( $opts[ $args['key'] ] ) : $args['default'];
                    printf(
                        '<input type="number" min="1" max="999" name="%s[%s]" value="%d" class="small-text" /><p class="description">%s</p>',
                        esc_attr( SettingsPage::OPTION_KEY ),
                        esc_attr( $args['key'] ),
                        $val,
                        esc_html( $args['description'] )
                    );
                },
                'hhb-settings-automation',
                'hhb_section_automation',
                $field
            );
        }

        // Section: Cancellation Policy
        add_settings_section(
            'hhb_section_cancellation',
            __( 'Cancellation & Refund Policy', 'himalayan-homestay-bookings' ),
            [ __CLASS__, 'render_section_cancellation' ],
            'hhb-settings-cancellation'
        );
    }

    public static function sanitize_settings( $input ) {
        if ( ! is_array( $input ) ) {
            return [];
        }
        
        $sanitized = get_option( self::OPTION_KEY, [] );
        $tab = $input['active_tab_submitting'] ?? 'payment_gateways';
        
        if ( $tab === 'payment_gateways' ) {
            $sanitized['razorpay_enabled'] = ! empty( $input['razorpay_enabled'] ) ? 'yes' : 'no';
            $sanitized['stripe_enabled']   = ! empty( $input['stripe_enabled'] ) ? 'yes' : 'no';
            $sanitized['fake_gateway_enabled'] = ! empty( $input['fake_gateway_enabled'] ) ? 'yes' : 'no';
            $sanitized['cash_mode_enabled'] = ! empty( $input['cash_mode_enabled'] ) ? 'yes' : 'no';
            
            if ( isset( $input['razorpay_key_id'] ) ) $sanitized['razorpay_key_id'] = sanitize_text_field( $input['razorpay_key_id'] );
            if ( isset( $input['razorpay_key_secret'] ) ) $sanitized['razorpay_key_secret'] = sanitize_text_field( $input['razorpay_key_secret'] );
            if ( isset( $input['razorpay_webhook_secret'] ) ) $sanitized['razorpay_webhook_secret'] = sanitize_text_field( $input['razorpay_webhook_secret'] );
            if ( isset( $input['stripe_publishable_key'] ) ) $sanitized['stripe_publishable_key'] = sanitize_text_field( $input['stripe_publishable_key'] );
            if ( isset( $input['stripe_secret_key'] ) ) $sanitized['stripe_secret_key'] = sanitize_text_field( $input['stripe_secret_key'] );
        } elseif ( $tab === 'gdpr_privacy' ) {
            if ( isset( $input['privacy_policy_url'] ) ) $sanitized['privacy_policy_url'] = esc_url_raw( $input['privacy_policy_url'] );
        } elseif ( $tab === 'email_templates' ) {
            $email_templates = [
                'booking_received', 'admin_new_booking', 'booking_approved',
                'payment_confirmed', 'payment_expired', 'pre_arrival',
                'post_checkout', 'review_followup', 'win_back_primary', 'win_back',
            ];
            foreach ( $email_templates as $k ) {
                if ( isset( $input["email_subject_{$k}"] ) ) $sanitized["email_subject_{$k}"] = sanitize_text_field( $input["email_subject_{$k}"] );
                if ( isset( $input["email_body_{$k}"] ) ) $sanitized["email_body_{$k}"] = wp_kses_post( $input["email_body_{$k}"] );
            }
        } elseif ( $tab === 'automation_timing' ) {
            $timing_keys = [ 'payment_expiry_minutes', 'review_followup_days', 'win_back_primary_days', 'win_back_secondary_days' ];
            foreach ( $timing_keys as $k ) {
                if ( isset( $input[ $k ] ) ) $sanitized[ $k ] = max( 1, intval( $input[ $k ] ) );
            }
        } elseif ( $tab === 'cancellation_policy' ) {
            $allowed_policies = [ 'flexible', 'moderate', 'strict', 'custom' ];
            $policy = sanitize_text_field( $input['cancellation_policy'] ?? 'flexible' );
            $sanitized['cancellation_policy'] = in_array( $policy, $allowed_policies, true ) ? $policy : 'flexible';
            $sanitized['cancellation_custom_pct']      = max( 0, min( 100, intval( $input['cancellation_custom_pct'] ?? 100 ) ) );
            $sanitized['cancellation_flexible_hours']   = max( 1, intval( $input['cancellation_flexible_hours'] ?? 24 ) );
            $sanitized['cancellation_moderate_days']    = max( 1, intval( $input['cancellation_moderate_days'] ?? 3 ) );
        }
        
        unset( $sanitized['active_tab_submitting'] );

        return $sanitized;
    }

    public static function render_section_automation(): void {
        echo '<p>' . esc_html__( 'Control when automated lifecycle emails are triggered. Changes take effect on the next cron run.', 'himalayan-homestay-bookings' ) . '</p>';
    }

    public static function render_section_fake(): void {
        echo '<p>' . __( '⚠️ For testing only. This gateway simulates a payment flow without real money.', 'himalayan-homestay-bookings' ) . '</p>';
    }

    public static function render_section_cancellation(): void {
        echo '<p>' . __( 'Configure when and how much to refund when a confirmed booking is cancelled. The refund percentage is calculated automatically by the system based on these settings.', 'himalayan-homestay-bookings' ) . '</p>';
    }

    public static function render_section_cash_mode(): void {
        echo '<p>' . esc_html__( 'Configure the Pay on Arrival (Cash) payment option.', 'himalayan-homestay-bookings' ) . '</p>';
    }

    public static function render_section_razorpay(): void {
        echo '<p>' . esc_html__( 'Configure Razorpay to accept payments predominantly in India.', 'himalayan-homestay-bookings' ) . '</p>';
    }

    public static function render_section_email_templates(): void {
        echo '<p>' . esc_html__( 'Customize the automated email subject lines and body content sent to your guests and admins. Leave empty to use default text.', 'himalayan-homestay-bookings' ) . '</p>';
        echo '<p><strong>Supported Placeholders:</strong> <code>{guest_name}</code>, <code>{property_name}</code>, <code>{check_in}</code>, <code>{check_out}</code>, <code>{total_price}</code>, <code>{booking_id}</code></p>';
    }

    public static function render_section_gdpr(): void {
        echo '<p>' . esc_html__( 'Configure privacy settings to comply with international regulations like GDPR and CCPA.', 'himalayan-homestay-bookings' ) . '</p>';
    }

    public static function render_section_stripe(): void {
        echo '<hr style="margin:40px 0;">';
        echo '<p>' . esc_html__( 'Configure Stripe to easily accept global credit and debit card payments.', 'himalayan-homestay-bookings' ) . '</p>';
    }


    public static function render_checkbox_field( array $args ): void {
        $options = get_option( self::OPTION_KEY, [] );
        $key     = $args['key'] ?? '';
        $label   = $args['label'] ?? '';
        $checked = isset( $options[ $key ] ) && 'yes' === $options[ $key ] ? 'checked' : '';
        
        if ( $key ) {
            printf(
                '<label><input type="checkbox" name="%1$s[%2$s]" value="yes" %3$s> %4$s</label>',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $key ),
                $checked,
                esc_html( $label )
            );
        }
    }

    public static function render_text_field( array $args ): void {
        $options = get_option( self::OPTION_KEY, [] );
        $key     = $args['key'] ?? '';
        $desc    = $args['description'] ?? '';
        $val     = $options[ $key ] ?? '';
        
        if ( $key ) {
            printf(
                '<input type="text" name="%1$s[%2$s]" value="%3$s" class="regular-text"><p class="description">%4$s</p>',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $key ),
                esc_attr( $val ),
                esc_html( $desc )
            );
        }
    }

    public static function render_password_field( array $args ): void {
        $options = get_option( self::OPTION_KEY, [] );
        $key     = $args['key'] ?? '';
        $desc    = $args['description'] ?? '';
        $val     = $options[ $key ] ?? '';
        
        if ( $key ) {
            printf(
                '<input type="password" name="%1$s[%2$s]" value="%3$s" class="regular-text"><p class="description">%4$s</p>',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $key ),
                esc_attr( $val ),
                esc_html( $desc )
            );
        }
    }

    public static function render_text_field_gdpr( array $args ): void {
        $options = get_option( self::OPTION_KEY, [] );
        $key     = $args['key'] ?? '';
        $desc    = $args['description'] ?? '';
        $val     = $options[ $key ] ?? '';
        
        if ( $key ) {
            printf(
                '<input type="url" name="%1$s[%2$s]" value="%3$s" class="regular-text"><p class="description">%4$s</p>',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $key ),
                esc_attr( $val ),
                esc_html( $desc )
            );
        }
    }

    public static function render_email_subject_field( array $args ): void {
        $options = get_option( self::OPTION_KEY, [] );
        $key     = $args['key'] ?? '';
        $val     = $options[ $key ] ?? '';
        
        if ( $key ) {
            printf(
                '<input type="text" name="%1$s[%2$s]" value="%3$s" class="large-text" placeholder="Default Subject Line">',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $key ),
                esc_attr( $val )
            );
        }
    }

    public static function render_email_body_field( array $args ): void {
        $options = get_option( self::OPTION_KEY, [] );
        $key     = $args['key'] ?? '';
        $val     = $options[ $key ] ?? '';
        
        if ( $key ) {
            printf(
                '<textarea name="%1$s[%2$s]" rows="5" class="large-text" placeholder="Default Email Content">%3$s</textarea><hr style="margin-top:20px; border:0; border-top:1px solid #ccc;">',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $key ),
                esc_textarea( $val )
            );
        }
    }

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // We can use a simple tabbed interface if we add more options later.
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'payment_gateways';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Himalayan Homestays Settings', 'himalayan-homestay-bookings' ); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?post_type=hhb_homestay&page=hhb-settings&tab=payment_gateways" class="nav-tab <?php echo $active_tab == 'payment_gateways' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Payment Gateways', 'himalayan-homestay-bookings' ); ?>
                </a>
                <a href="?post_type=hhb_homestay&page=hhb-settings&tab=cancellation_policy" class="nav-tab <?php echo $active_tab == 'cancellation_policy' ? 'nav-tab-active' : ''; ?>">
                    🔄 <?php esc_html_e( 'Cancellation Policy', 'himalayan-homestay-bookings' ); ?>
                </a>
                <a href="?post_type=hhb_homestay&page=hhb-settings&tab=email_templates" class="nav-tab <?php echo $active_tab == 'email_templates' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Email Templates', 'himalayan-homestay-bookings' ); ?>
                </a>
                <a href="?post_type=hhb_homestay&page=hhb-settings&tab=gdpr_privacy" class="nav-tab <?php echo $active_tab == 'gdpr_privacy' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'GDPR & Privacy', 'himalayan-homestay-bookings' ); ?>
                </a>
                <a href="?post_type=hhb_homestay&page=hhb-settings&tab=smtp_config" class="nav-tab <?php echo $active_tab == 'smtp_config' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'SMTP Config', 'himalayan-homestay-bookings' ); ?>
                </a>
                <a href="?post_type=hhb_homestay&page=hhb-settings&tab=cron_automation" class="nav-tab <?php echo $active_tab == 'cron_automation' ? 'nav-tab-active' : ''; ?>">
                    ⏱️ <?php esc_html_e( 'Cron & Automation', 'himalayan-homestay-bookings' ); ?>
                </a>
                <a href="?post_type=hhb_homestay&page=hhb-settings&tab=automation_timing" class="nav-tab <?php echo $active_tab == 'automation_timing' ? 'nav-tab-active' : ''; ?>">
                    ⚙️ <?php esc_html_e( 'Timing Settings', 'himalayan-homestay-bookings' ); ?>
                </a>
            </h2>

            <?php if ( isset($_GET['config_saved']) ) : ?>
                <div class="notice notice-success is-dismissible"><p><strong>Success:</strong> SMTP configuration saved securely.</p></div>
            <?php endif; ?>

            <div style="padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 20px;">
                <?php if ( in_array( $active_tab, array( 'payment_gateways', 'email_templates', 'gdpr_privacy', 'automation_timing', 'cancellation_policy' ) ) ) : ?>
                    <form action="options.php" method="post">
                        <?php
                        settings_fields( 'hhb_settings_group' );
                        ?>
                        <input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[active_tab_submitting]" value="<?php echo esc_attr( $active_tab ); ?>">
                        <?php

                        if ( $active_tab == 'payment_gateways' ) {
                            do_settings_sections( 'hhb-settings' );
                        } elseif ( $active_tab == 'cancellation_policy' ) {
                            do_settings_sections( 'hhb-settings-cancellation' );
                            $opts = get_option( self::OPTION_KEY, [] );
                            $policy      = $opts['cancellation_policy'] ?? 'flexible';
                            $custom_pct  = $opts['cancellation_custom_pct'] ?? 100;
                            $flex_hours  = $opts['cancellation_flexible_hours'] ?? 24;
                            $mod_days    = $opts['cancellation_moderate_days'] ?? 3;
                            $opt_key     = esc_attr( self::OPTION_KEY );
                            ?>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Cancellation Policy', 'himalayan-homestay-bookings' ); ?></th>
                                    <td>
                                        <fieldset>
                                            <label style="display:block;margin-bottom:8px;">
                                                <input type="radio" name="<?php echo $opt_key; ?>[cancellation_policy]" value="flexible" <?php checked( $policy, 'flexible' ); ?> />
                                                <strong><?php esc_html_e( 'Flexible', 'himalayan-homestay-bookings' ); ?></strong>
                                                &mdash; <?php esc_html_e( '100% refund if cancelled before the cutoff window below', 'himalayan-homestay-bookings' ); ?>
                                            </label>
                                            <label style="display:block;margin-bottom:8px;">
                                                <input type="radio" name="<?php echo $opt_key; ?>[cancellation_policy]" value="moderate" <?php checked( $policy, 'moderate' ); ?> />
                                                <strong><?php esc_html_e( 'Moderate', 'himalayan-homestay-bookings' ); ?></strong>
                                                &mdash; <?php esc_html_e( '50% refund if cancelled before the cutoff window below', 'himalayan-homestay-bookings' ); ?>
                                            </label>
                                            <label style="display:block;margin-bottom:8px;">
                                                <input type="radio" name="<?php echo $opt_key; ?>[cancellation_policy]" value="strict" <?php checked( $policy, 'strict' ); ?> />
                                                <strong><?php esc_html_e( 'Strict', 'himalayan-homestay-bookings' ); ?></strong>
                                                &mdash; <?php esc_html_e( 'No refund (0%) once confirmed', 'himalayan-homestay-bookings' ); ?>
                                            </label>
                                            <label style="display:block;margin-bottom:8px;">
                                                <input type="radio" name="<?php echo $opt_key; ?>[cancellation_policy]" value="custom" <?php checked( $policy, 'custom' ); ?> />
                                                <strong><?php esc_html_e( 'Custom', 'himalayan-homestay-bookings' ); ?></strong>
                                                &mdash; <?php esc_html_e( 'Set your own refund percentage below', 'himalayan-homestay-bookings' ); ?>
                                            </label>
                                        </fieldset>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Custom Refund %', 'himalayan-homestay-bookings' ); ?></th>
                                    <td>
                                        <input type="number" min="0" max="100" name="<?php echo $opt_key; ?>[cancellation_custom_pct]" value="<?php echo intval( $custom_pct ); ?>" class="small-text" /> %
                                        <p class="description"><?php esc_html_e( 'Only used when Custom policy is selected. E.g. 75 = guest gets 75% back.', 'himalayan-homestay-bookings' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Flexible: Min Hours Before Check-in', 'himalayan-homestay-bookings' ); ?></th>
                                    <td>
                                        <input type="number" min="1" max="720" name="<?php echo $opt_key; ?>[cancellation_flexible_hours]" value="<?php echo intval( $flex_hours ); ?>" class="small-text" /> <?php esc_html_e( 'hours', 'himalayan-homestay-bookings' ); ?>
                                        <p class="description"><?php esc_html_e( 'Guest gets 100% refund if they cancel at least this many hours before check-in. Within this window = no refund. Default: 24.', 'himalayan-homestay-bookings' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Moderate: Min Days Before Check-in', 'himalayan-homestay-bookings' ); ?></th>
                                    <td>
                                        <input type="number" min="1" max="90" name="<?php echo $opt_key; ?>[cancellation_moderate_days]" value="<?php echo intval( $mod_days ); ?>" class="small-text" /> <?php esc_html_e( 'days', 'himalayan-homestay-bookings' ); ?>
                                        <p class="description"><?php esc_html_e( 'Guest gets 50% refund if they cancel at least this many days before check-in. Within this window = no refund. Default: 3.', 'himalayan-homestay-bookings' ); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <?php
                        } elseif ( $active_tab == 'email_templates' ) {
                            $opts = get_option( self::OPTION_KEY, [] );
                            $email_groups = [
                                '📩 Request &amp; Approval' => [
                                    'booking_received'  => [ 'label' => 'Booking Received (Customer)', 'hint' => 'Sent immediately when a guest submits the booking form.' ],
                                    'admin_new_booking' => [ 'label' => 'New Booking Alert (Admin)',   'hint' => 'Sent to admin when a new booking request arrives.' ],
                                    'booking_approved'  => [ 'label' => 'Booking Approved — Payment Pending (Customer)', 'hint' => 'Sent when admin approves the booking. Contains payment link.' ],
                                ],
                                '💳 Payment' => [
                                    'payment_confirmed' => [ 'label' => 'Payment Confirmed (Customer)', 'hint' => 'Sent on successful payment. Includes PDF invoice.' ],
                                    'payment_expired'   => [ 'label' => 'Payment Expired (Customer)',   'hint' => 'Sent when the 60-minute payment window closes without payment.' ],
                                ],
                                '🏡 Stay Lifecycle' => [
                                    'pre_arrival'       => [ 'label' => 'Pre-arrival Guide (Customer)', 'hint' => 'Sent 3 days before check-in with directions, rules, and tips.' ],
                                    'post_checkout'     => [ 'label' => 'Review Request (Customer)',    'hint' => 'Sent 1 day after checkout asking for a review.' ],
                                    'review_followup'   => [ 'label' => 'Review Follow-up Reminder (Customer)', 'hint' => 'Sent if no review after first request (configurable delay, default 5 days after checkout).' ],
                                ],
                                '🔁 Re-engagement' => [
                                    'win_back_primary'  => [ 'label' => 'Returning Guest Offer — Primary (Customer)',  'hint' => 'Sent after configurable days (default 60) with a special discount code.' ],
                                    'win_back'          => [ 'label' => 'Seasonal Win-back Campaign (Customer)', 'hint' => 'Sent after configurable days (default 180) for festival/seasonal re-engagement.' ],
                                ],
                            ];

                            echo '<style>
                                .hhb-email-group { margin-bottom: 24px; }
                                .hhb-email-group h3 { margin: 0 0 10px; font-size: 14px; color: #333; border-bottom: 2px solid #e0e0e0; padding-bottom: 6px; }
                                .hhb-email-card { border: 1px solid #ddd; border-radius: 6px; margin-bottom: 10px; overflow: hidden; }
                                .hhb-email-card summary { padding: 12px 16px; cursor: pointer; background: #fafafa; font-weight: 600; font-size: 13px; list-style: none; display: flex; align-items: center; gap: 8px; }
                                .hhb-email-card summary::-webkit-details-marker { display: none; }
                                .hhb-email-card summary::before { content: "▶"; font-size: 10px; color: #888; transition: transform 0.2s; }
                                .hhb-email-card[open] summary::before { transform: rotate(90deg); }
                                .hhb-email-card summary:hover { background: #f0f0f0; }
                                .hhb-email-card-body { padding: 16px 20px; border-top: 1px solid #eee; background: #fff; }
                                .hhb-email-hint { color: #888; font-size: 12px; margin: 0 0 14px; }
                                .hhb-email-label { font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.4px; color: #555; margin-bottom: 4px; }
                                .hhb-email-field { width: 100%; margin-bottom: 14px; }
                                .hhb-email-field input { width: 100%; }
                                .hhb-email-field textarea { width: 100%; }
                            </style>';

                            foreach ( $email_groups as $group_label => $templates ) {
                                echo '<div class="hhb-email-group"><h3>' . $group_label . '</h3>';
                                foreach ( $templates as $key => $info ) {
                                    $subj_key = "email_subject_{$key}";
                                    $body_key = "email_body_{$key}";
                                    $subj_val = esc_attr( $opts[ $subj_key ] ?? '' );
                                    $body_val = esc_textarea( $opts[ $body_key ] ?? '' );
                                    echo '<details class="hhb-email-card">';
                                    echo '<summary>' . esc_html( $info['label'] ) . '</summary>';
                                    echo '<div class="hhb-email-card-body">';
                                    echo '<p class="hhb-email-hint">' . esc_html( $info['hint'] ) . '</p>';
                                    printf(
                                        '<div class="hhb-email-field"><div class="hhb-email-label">Subject Line</div><input type="text" name="%s[%s]" value="%s" placeholder="Default subject used if blank" class="large-text" /></div>',
                                        esc_attr( self::OPTION_KEY ), esc_attr( $subj_key ), $subj_val
                                    );
                                    printf(
                                        '<div class="hhb-email-field"><div class="hhb-email-label">Message Body</div><textarea name="%s[%s]" rows="5" class="large-text" placeholder="Default body used if blank. Placeholders: {guest_name} {property_name} {check_in} {check_out} {total_price} {booking_id}">%s</textarea></div>',
                                        esc_attr( self::OPTION_KEY ), esc_attr( $body_key ), $body_val
                                    );
                                    echo '</div></details>';
                                }
                                echo '</div>';
                            }
                        } elseif ( $active_tab == 'gdpr_privacy' ) {
                            do_settings_sections( 'hhb-settings-gdpr' );
                        } elseif ( $active_tab == 'automation_timing' ) {
                            do_settings_sections( 'hhb-settings-automation' );
                        }
                        
                        submit_button( __( 'Save Settings', 'himalayan-homestay-bookings' ) );
                        ?>
                    </form>
                
                <?php elseif ( $active_tab == 'cron_automation' ) : ?>
                    <?php self::render_cron_tab(); ?>

                <?php elseif ( $active_tab == 'smtp_config' ) : ?>
                    
                    <h3>SMTP Configuration (Gmail)</h3>
                    <p>Enter your Gmail address and the 16-character App Password to allow the local server to send emails. <br>These are saved securely in the database.</p>
                    
                    <form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
                        <?php wp_nonce_field('hhb_save_smtp_config_nonce', 'security'); ?>
                        <input type="hidden" name="action" value="hhb_save_smtp_config">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="smtp_email">Gmail Address</label></th>
                                <td>
                                    <input type="email" name="smtp_email" id="smtp_email" class="regular-text" value="<?php echo esc_attr( get_option('hhb_smtp_email') ); ?>" placeholder="youremail@gmail.com">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="smtp_pass">App Password</label></th>
                                <td>
                                    <input type="password" name="smtp_pass" id="smtp_pass" class="regular-text" value="<?php echo esc_attr( get_option('hhb_smtp_pass') ); ?>" placeholder="16-character app password">
                                    <p class="description">Do not use your regular Gmail password. Generate an <a href="https://myaccount.google.com/apppasswords" target="_blank">App Password here</a>.</p>
                                </td>
                            </tr>
                        </table>
                        
                        <div style="margin-top: 20px;">
                            <?php submit_button( 'Save SMTP Settings', 'primary', 'submit', false ); ?>
                        </div>
                    </form>

                <?php else: ?>
                    
                    <form action="options.php" method="post">
                        <?php
                        if ( $active_tab == 'payment_gateways' ) {
                            settings_fields( 'hhb_settings_group' );
                            do_settings_sections( 'hhb-settings' );
                            submit_button();
                        } elseif ( $active_tab == 'gdpr_privacy' ) {
                            settings_fields( 'hhb_settings_group' );
                            do_settings_sections( 'hhb-settings-gdpr' );
                            submit_button();
                        }
                        ?>
                    </form>
                    
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // Cron & Automation Tab
    // =========================================================================

    private static function render_cron_tab(): void {
        // Auto-schedule missing cron jobs on page load if not already scheduled.
        if ( ! wp_next_scheduled( 'hhb_sync_ical_feeds' ) ) {
            wp_schedule_event( time(), 'fifteen_minutes', 'hhb_sync_ical_feeds' );
        }
        if ( ! wp_next_scheduled( 'himalayan_cleanup_expired_holds' ) ) {
            wp_schedule_event( time(), 'five_minutes', 'himalayan_cleanup_expired_holds' );
        }

        $jobs = [
            [
                'hook'        => 'hhb_daily_email_automator',
                'label'       => '📧 Daily Email Automator',
                'description' => 'Sends pre-arrival (3 days before), post-checkout review request (1 day after), review follow-up (5 days after), win-back primary (60 days), and seasonal win-back (180 days). Runs once every 24 hours.',
                'schedule'    => 'daily',
            ],
            [
                'hook'        => 'hhb_check_payment_expiry',
                'label'       => '💳 Payment Expiry Checker',
                'description' => 'Automatically expires approved bookings where the payment window has passed and the guest has not paid. Releases dates and sends expiry notification. Runs every 5 minutes.',
                'schedule'    => 'five_minutes',
            ],
            [
                'hook'        => 'hhb_sync_ical_feeds',
                'label'       => '📅 iCal Feed Sync',
                'description' => 'Fetches and syncs external calendar feeds (Airbnb, Booking.com etc.) into the booking system to block out already-booked dates. Runs every 15 minutes.',
                'schedule'    => 'fifteen_minutes',
            ],
            [
                'hook'        => 'himalayan_cleanup_expired_holds',
                'label'       => '🗑️ Expired Holds Cleanup',
                'description' => 'Releases date holds that have expired (e.g. guest abandoned checkout). Frees up availability immediately. Runs every 5 minutes.',
                'schedule'    => 'five_minutes',
            ],
        ];

        $ran_job    = isset( $_GET['cron_ran'] )    ? sanitize_text_field( $_GET['cron_ran'] )    : '';
        $cron_error = isset( $_GET['cron_error'] )  ? sanitize_text_field( $_GET['cron_error'] )  : '';

        if ( $ran_job ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Ran:</strong> ' . esc_html( $ran_job ) . ' executed successfully.</p></div>';
        }
        if ( $cron_error ) {
            echo '<div class="notice notice-error is-dismissible"><p><strong>❌ Error:</strong> ' . esc_html( $cron_error ) . '</p></div>';
        }
        ?>
        <h3><?php esc_html_e( 'Scheduled Cron Jobs', 'himalayan-homestay-bookings' ); ?></h3>
        <p><?php esc_html_e( 'Monitor and manually trigger background automation jobs. Each job runs automatically on WP-Cron, but you can run any of them immediately using the button below.', 'himalayan-homestay-bookings' ); ?></p>

        <style>
            .hhb-cron-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
            .hhb-cron-card { background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 20px; position: relative; }
            .hhb-cron-card h4 { margin: 0 0 8px; font-size: 15px; }
            .hhb-cron-card p { margin: 0 0 12px; color: #555; font-size: 13px; line-height: 1.5; }
            .hhb-cron-meta { font-size: 12px; color: #888; margin-bottom: 14px; }
            .hhb-cron-meta span { display: inline-block; margin-right: 12px; }
            .hhb-cron-meta .hhb-badge { background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
            .hhb-cron-meta .hhb-badge.warn { background: #fff3cd; color: #856404; }
            .hhb-cron-run-btn { background: #2271b1; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-block; }
            .hhb-cron-run-btn:hover { background: #135e96; color: #fff; }
        </style>

        <div class="hhb-cron-grid">
        <?php foreach ( $jobs as $job ) :
            $next_ts  = wp_next_scheduled( $job['hook'] );
            $next_str = $next_ts ? human_time_diff( time(), $next_ts ) . ' from now' : 'Not scheduled';
            $scheduled = (bool) $next_ts;

            // Derive last run from email log or transient
            $last_ran = get_option( 'hhb_cron_last_ran_' . $job['hook'], false );
            $last_str = $last_ran ? human_time_diff( $last_ran, time() ) . ' ago' : 'Never recorded';
        ?>
            <div class="hhb-cron-card">
                <h4><?php echo esc_html( $job['label'] ); ?></h4>
                <p><?php echo esc_html( $job['description'] ); ?></p>
                <div class="hhb-cron-meta">
                    <span class="hhb-badge <?php echo $scheduled ? '' : 'warn'; ?>">
                        <?php echo $scheduled ? '✅ Scheduled' : '⚠️ Not Scheduled'; ?>
                    </span><br><br>
                    <span>⏰ <strong>Next run:</strong> <?php echo esc_html( $next_str ); ?></span><br>
                    <span>🕐 <strong>Last run:</strong> <?php echo esc_html( $last_str ); ?></span>
                </div>
                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline;">
                    <?php wp_nonce_field( 'hhb_run_cron_nonce', 'security' ); ?>
                    <input type="hidden" name="action" value="hhb_run_cron">
                    <input type="hidden" name="cron_hook" value="<?php echo esc_attr( $job['hook'] ); ?>">
                    <button type="submit" class="hhb-cron-run-btn">▶ Run Now</button>
                </form>
            </div>
        <?php endforeach; ?>
        </div>
        <?php
    }

    public static function handle_run_cron(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized.' );
        }
        if ( ! isset( $_REQUEST['security'] ) || ! wp_verify_nonce( $_REQUEST['security'], 'hhb_run_cron_nonce' ) ) {
            wp_die( 'Security check failed.' );
        }

        $allowed_hooks = [
            'hhb_daily_email_automator',
            'hhb_sync_ical_feeds',
            'himalayan_cleanup_expired_holds',
        ];

        $hook = sanitize_text_field( $_POST['cron_hook'] ?? '' );

        if ( ! in_array( $hook, $allowed_hooks, true ) ) {
            wp_redirect( admin_url('edit.php?post_type=hhb_homestay&page=hhb-settings&tab=cron_automation&cron_error=Invalid+job+name') );
            exit;
        }

        // Fire the cron action immediately
        do_action( $hook );

        // Record last run time
        update_option( 'hhb_cron_last_ran_' . $hook, time() );

        wp_redirect( admin_url('edit.php?post_type=hhb_homestay&page=hhb-settings&tab=cron_automation&cron_ran=' . urlencode( $hook ) ) );
        exit;
    }

    public static function handle_save_smtp_config() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized.' );
        }
        if ( ! isset( $_REQUEST['security'] ) || ! wp_verify_nonce( $_REQUEST['security'], 'hhb_save_smtp_config_nonce' ) ) {
            wp_die( 'Security check failed.' );
        }

        $email = sanitize_email( $_POST['smtp_email'] ?? '' );
        $pass  = sanitize_text_field( str_replace(' ', '', $_POST['smtp_pass'] ?? '') );
        
        update_option( 'hhb_smtp_email', $email );
        update_option( 'hhb_smtp_pass', $pass );

        wp_redirect( admin_url('edit.php?post_type=hhb_homestay&page=hhb-settings&tab=smtp_config&config_saved=1') );
        exit;
    }
}
