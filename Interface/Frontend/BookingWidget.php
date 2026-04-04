<?php
/**
 * Frontend Booking Widget
 *
 * Renders the booking form on single homestay pages as a shortcode.
 * Features AJAX availability checking, detailed price breakdown with
 * extra services, and deposit/balance display.
 *
 * @package Himalayan\Homestay\Interface\Frontend
 */

namespace Himalayan\Homestay\Interface\Frontend;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class BookingWidget {

    public static function init(): void {
        add_shortcode( 'hhb_booking_form', [ __CLASS__, 'render_widget' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_ajax_nopriv_hhb_ajax_login',    [ __CLASS__, 'ajax_login' ] );
        add_action( 'wp_ajax_nopriv_hhb_ajax_register', [ __CLASS__, 'ajax_register' ] );
        add_action( 'wp_ajax_nopriv_hhb_nonce_refresh', [ __CLASS__, 'ajax_nonce_refresh' ] );
        add_action( 'wp_ajax_hhb_nonce_refresh',        [ __CLASS__, 'ajax_nonce_refresh' ] );
    }

    public static function ajax_login(): void {
        check_ajax_referer( 'hhb_auth_action', 'hhb_auth_nonce' );

        $log = sanitize_user( wp_unslash( $_POST['log'] ?? '' ) );
        $pwd = wp_unslash( $_POST['pwd'] ?? '' );

        if ( empty( $log ) || empty( $pwd ) ) {
            wp_send_json_error( [ 'message' => __( 'Please enter your username and password.', 'himalayan-homestay-bookings' ) ] );
        }

        $creds = [
            'user_login'    => $log,
            'user_password' => $pwd,
            'remember'      => ! empty( $_POST['rememberme'] ),
        ];

        $user = wp_signon( $creds, is_ssl() );

        if ( is_wp_error( $user ) ) {
            wp_send_json_error( [ 'message' => $user->get_error_message() ] );
        }

        // Set current user so the nonce is generated for the now-logged-in session.
        wp_set_current_user( $user->ID );

        wp_send_json_success( [
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'name'  => $user->display_name,
            'email' => $user->user_email,
        ] );
    }

    public static function ajax_register(): void {
        check_ajax_referer( 'hhb_auth_action', 'hhb_auth_nonce' );

        $email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $password = wp_unslash( $_POST['reg_password'] ?? '' );

        if ( ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => __( 'Please enter a valid email address.', 'himalayan-homestay-bookings' ) ] );
        }

        if ( empty( $password ) ) {
            wp_send_json_error( [ 'message' => __( 'Please choose a password.', 'himalayan-homestay-bookings' ) ] );
        }

        if ( email_exists( $email ) ) {
            wp_send_json_error( [ 'message' => __( 'An account with this email already exists. Please log in instead.', 'himalayan-homestay-bookings' ) ] );
        }

        // Use the part before @ as username; make it unique if taken.
        $username = sanitize_user( strstr( $email, '@', true ) );
        if ( username_exists( $username ) ) {
            $username .= wp_rand( 10, 99 );
        }

        $user_id = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( [ 'message' => $user_id->get_error_message() ] );
        }

        $user_obj = new \WP_User( $user_id );
        $user_obj->set_role( 'hhb_guest' );

        wp_set_auth_cookie( $user_id, true );
        wp_set_current_user( $user_id );

        wp_send_json_success( [
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'name'  => $user_obj->display_name ?: $username,
            'email' => $email,
        ] );
    }

    public static function ajax_nonce_refresh(): void {
        $user  = wp_get_current_user();
        wp_send_json_success( [
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'name'  => $user->exists() ? $user->display_name : '',
            'email' => $user->exists() ? $user->user_email   : '',
        ] );
    }

    public static function enqueue_assets(): void {
        wp_enqueue_style( 'flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css' );
        wp_enqueue_script( 'flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true );
    }

    public static function render_widget( $atts ): string {
        $homestay_id = get_the_ID();

        $booking_enabled = get_post_meta( $homestay_id, 'hhb_booking_enabled', true );
        if ( 'no' === $booking_enabled ) {
            return '<div class="hhb-booking-widget-disabled" style="padding: 25px 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">' . 
                   '<h3 style="margin: 0 0 10px; font-size: 20px; color: #1e293b; font-weight: 700;">' . esc_html__( 'Bookings Currently Closed', 'himalayan-homestay-bookings' ) . '</h3>' .
                   '<p style="margin: 0; font-size: 14px; color: #64748b; line-height: 1.5;">' . esc_html__( 'This property is not accepting new bookings at the moment. Please check back later or contact the host for inquiries.', 'himalayan-homestay-bookings' ) . '</p>' .
                   '</div>';
        }
        $currency    = 'INR';
        $min_nights  = get_post_meta( $homestay_id, 'hhb_min_nights', true ) ?: 1;
        $deposit_pct = get_post_meta( $homestay_id, 'hhb_deposit_percent', true ) ?: 0;
        
        // Currency symbol and unit for display
        $currency_symbols = [ 'USD' => '$', 'INR' => '₹', 'EUR' => '€', 'GBP' => '£', 'NPR' => 'रु' ];
        $currency_symbol  = isset( $currency_symbols[ strtoupper($currency) ] ) ? $currency_symbols[ strtoupper($currency) ] : $currency;
        $currency_unit    = 'night';

        // Fetch post author data for host profile (Phase 12 addition)
        $post_author_id = get_post_field( 'post_author', $homestay_id );
        
        $host_name   = get_post_meta( $homestay_id, 'hhb_host_name', true ) ?: get_the_author_meta( 'display_name', $post_author_id );
        $host_email  = get_post_meta( $homestay_id, 'hhb_host_email', true ) ?: get_the_author_meta( 'user_email', $post_author_id );
        
        $host_phone  = get_post_meta( $homestay_id, 'hhb_host_phone', true );
        if ( empty( $host_phone ) ) $host_phone = get_the_author_meta( 'hhb_phone_number', $post_author_id );
        if ( empty( $host_phone ) ) $host_phone = get_user_meta( $post_author_id, 'billing_phone', true );

        $host_avatar = get_post_meta( $homestay_id, 'hhb_host_avatar_url', true );
        if ( empty( $host_avatar ) ) $host_avatar = get_avatar_url( $post_author_id, [ 'size' => 96 ] );

        // Fetch extra services
        global $wpdb;
        $services_table = $wpdb->prefix . 'himalayan_extra_services';
        $services = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$services_table} WHERE (homestay_id = %d OR homestay_id = 0) AND is_active = 1 ORDER BY sort_order",
            $homestay_id
        ) );

        // Fetch Rooms mapping
        $rooms = get_children( [
            'post_parent' => $homestay_id,
            'post_type'   => 'hhb_room',
            'post_status' => 'publish',
            'numberposts' => -1,
        ] );

        $rooms_data = [];
        foreach($rooms as $r) {
             $rooms_data[] = [
                  'id'         => $r->ID,
                  'name'       => $r->post_title,
                  'base_price' => (float) get_post_meta($r->ID, 'room_base_price', true),
                  'max_guests' => (int) get_post_meta($r->ID, 'room_max_guests', true) ?: 2,
                  'quantity'   => (int) get_post_meta($r->ID, 'room_quantity', true) ?: 1,
             ];
        }

        // Fetch aggregate blocked dates for Homestay (Fallback logic for date picker)
        // A more advanced integration later would use the /check-availability endpoint on room change.
        $bookings_table = $wpdb->prefix . 'himalayan_bookings';
        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT check_in, check_out FROM {$bookings_table} 
            WHERE homestay_id = %d 
            AND status IN ('pending', 'approved', 'confirmed')
            AND (payment_expires_at IS NULL OR payment_expires_at > %s)",
            $homestay_id, current_time( 'mysql', 1 )
        ) );

        $booked_dates = [];
        foreach ( $bookings as $b ) {
            $period = new \DatePeriod(
                new \DateTime( $b->check_in ),
                new \DateInterval( 'P1D' ),
                new \DateTime( $b->check_out )
            );
            foreach ( $period as $date ) {
                $booked_dates[] = $date->format( 'Y-m-d' );
            }
        }
        $booked_dates_json = wp_json_encode( array_values( array_unique( $booked_dates ) ) );


        ob_start();
        ?>
        <div class="hhb-booking-widget"
             data-id="<?php echo esc_attr( $homestay_id ); ?>"
             data-currency="<?php echo esc_attr( $currency ); ?>"
             data-min-nights="<?php echo esc_attr( $min_nights ); ?>"
             data-deposit-pct="<?php echo esc_attr( $deposit_pct ); ?>"
             data-currency-symbol="<?php echo esc_attr( $currency_symbol ); ?>"
             data-currency-unit="<?php echo esc_attr( $currency_unit ); ?>"
             data-homestay-name="<?php echo esc_attr( get_the_title( $homestay_id ) ); ?>"
             data-homestay-link="<?php echo esc_url( get_permalink( $homestay_id ) ); ?>"
             data-host-name="<?php echo esc_attr( $host_name ); ?>"
             data-host-email="<?php echo esc_attr( $host_email ); ?>"
             data-host-phone="<?php echo esc_attr( $host_phone ); ?>"
             data-host-avatar="<?php echo esc_url( $host_avatar ); ?>"
             data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
             data-booked-dates="<?php echo esc_attr( $booked_dates_json ); ?>"
             data-nonce="<?php echo esc_attr( wp_create_nonce( 'hhb_booking_nonce' ) ); ?>">

            <form id="hhb-booking-form">
                
                <!-- Room Selection -->
                <div class="hhb-field">
                    <label><?php esc_html_e( 'Select Option', 'himalayan-homestay-bookings' ); ?></label>
                    <select id="hhb-room-select" required>
                        <option value=""><?php esc_html_e( '-- Choose a Room --', 'himalayan-homestay-bookings' ); ?></option>
                        <?php foreach($rooms_data as $rd): ?>
                            <option value="<?php echo esc_attr($rd['id']); ?>" data-guests="<?php echo esc_attr($rd['max_guests']); ?>" data-price="<?php echo esc_attr($rd['base_price']); ?>">
                                <?php echo esc_html($rd['name']) . ' (' . $currency_symbol . ' ' . $rd['base_price'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date Fields -->
                <div class="hhb-dates">
                    <div class="hhb-field">
                        <label><?php esc_html_e( 'Check-in', 'himalayan-homestay-bookings' ); ?></label>
                        <input type="text" id="hhb-check-in" required placeholder="<?php esc_attr_e( 'Select date', 'himalayan-homestay-bookings' ); ?>">
                    </div>
                    <div class="hhb-field">
                        <label><?php esc_html_e( 'Check-out', 'himalayan-homestay-bookings' ); ?></label>
                        <input type="text" id="hhb-check-out" required placeholder="<?php esc_attr_e( 'Select date', 'himalayan-homestay-bookings' ); ?>">
                    </div>
                </div>

                <!-- Guests & Quantities -->
                <div class="hhb-dates">
                    <div class="hhb-field">
                        <label><?php esc_html_e( 'Guests', 'himalayan-homestay-bookings' ); ?></label>
                        <select id="hhb-guests">
                            <option value="1">1 Guest</option>
                        </select>
                    </div>
                    <!-- (Optional) Room Quantity field if multiple quantities supported per booking -->
                </div>

                <!-- Extra Services -->
                <?php if ( ! empty( $services ) ) : ?>
                <div class="hhb-extras" style="margin-bottom:15px">
                    <label class="hhb-section-label"><?php esc_html_e( 'Extras & Add-ons', 'himalayan-homestay-bookings' ); ?></label>
                    <?php foreach ( $services as $svc ) :
                        $type_label = ucwords( str_replace( '_', ' ', $svc->price_type ) );
                    ?>
                        <label class="hhb-extra-item">
                            <input type="checkbox" class="hhb-service-cb" value="<?php echo esc_attr( $svc->id ); ?>">
                            <span class="hhb-extra-name"><?php echo esc_html( $svc->service_name ); ?></span>
                            <span class="hhb-extra-price"><?php echo esc_html( $currency . ' ' . number_format( $svc->price, 2 ) ); ?> (<?php echo esc_html( $type_label ); ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Customer Details (shown after availability check) -->
                <div class="hhb-customer-details" style="display:none">
                    <div class="hhb-field">
                        <label><?php esc_html_e( 'Full Name', 'himalayan-homestay-bookings' ); ?></label>
                        <input type="text" id="hhb-name" placeholder="John Doe">
                    </div>
                    <div class="hhb-field">
                        <label><?php esc_html_e( 'Email Address', 'himalayan-homestay-bookings' ); ?></label>
                        <input type="email" id="hhb-email" placeholder="john@example.com">
                    </div>
                    <div class="hhb-field">
                        <label><?php esc_html_e( 'Phone Number', 'himalayan-homestay-bookings' ); ?></label>
                        <input type="text" id="hhb-phone" placeholder="+91 98765 43210">
                    </div>
                    <div class="hhb-field">
                        <label><?php esc_html_e( 'Special Requests', 'himalayan-homestay-bookings' ); ?></label>
                        <textarea id="hhb-notes" rows="2" placeholder="<?php esc_attr_e( 'Any special requests or notes...', 'himalayan-homestay-bookings' ); ?>"></textarea>
                    </div>
                </div>

                <!-- Coupon Code (shown after availability check) -->
                <div class="hhb-coupon-section" id="hhb-coupon-section" style="display:none; margin-top:15px; margin-bottom:15px; padding-top:15px; border-top:1px dashed #e2e8f0;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px; color:#475569;"><?php esc_html_e( 'Promo Code', 'himalayan-homestay-bookings' ); ?></label>
                    <div class="hhb-field" style="margin-bottom:0; display:flex; gap:10px;">
                        <input type="text" id="hhb-coupon" placeholder="<?php esc_attr_e( 'Enter code here...', 'himalayan-homestay-bookings' ); ?>" style="flex:1; text-transform:uppercase;">
                        <button type="button" id="hhb-apply-coupon" class="hhb-btn" style="padding:0 15px; width:auto;"><?php esc_html_e( 'Apply', 'himalayan-homestay-bookings' ); ?></button>
                    </div>
                </div>

                <!-- Detailed Price Breakdown -->
                <div class="hhb-price-breakdown" style="display:none">
                    <div class="hhb-line" id="hhb-line-nightly">
                        <span class="hhb-line-label"></span>
                        <span class="hhb-line-value"></span>
                    </div>
                    <div class="hhb-line" id="hhb-line-extra-guest" style="display:none">
                        <span class="hhb-line-label"><?php esc_html_e( 'Extra Guest Fee', 'himalayan-homestay-bookings' ); ?></span>
                        <span class="hhb-line-value"></span>
                    </div>
                    <div class="hhb-line" id="hhb-line-services" style="display:none">
                        <span class="hhb-line-label"><?php esc_html_e( 'Add-on Services', 'himalayan-homestay-bookings' ); ?></span>
                        <span class="hhb-line-value"></span>
                    </div>
                    <div class="hhb-line" id="hhb-line-coupon" style="display:none; color: #16a34a; font-weight: 600;">
                        <span class="hhb-line-label"><?php esc_html_e( 'Discount', 'himalayan-homestay-bookings' ); ?> <small id="hhb-applied-coupon-name"></small></span>
                        <span class="hhb-line-value"></span>
                    </div>
                    <div class="hhb-line hhb-line-total">
                        <span class="hhb-line-label"><?php esc_html_e( 'Total', 'himalayan-homestay-bookings' ); ?></span>
                        <span class="hhb-line-value" id="hhb-total-price"></span>
                    </div>
                    <?php if ( $deposit_pct > 0 && $deposit_pct < 100 ) : ?>
                    <div class="hhb-line hhb-deposit-line">
                        <span class="hhb-line-label"><?php printf( esc_html__( 'Deposit (%d%%)', 'himalayan-homestay-bookings' ), $deposit_pct ); ?></span>
                        <span class="hhb-line-value" id="hhb-deposit-amount"></span>
                    </div>
                    <div class="hhb-line">
                        <span class="hhb-line-label"><?php esc_html_e( 'Balance Due at Check-in', 'himalayan-homestay-bookings' ); ?></span>
                        <span class="hhb-line-value" id="hhb-balance-due"></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="hhb-messages" id="hhb-messages"></div>

                <button type="button" id="hhb-check-btn" class="hhb-btn"><?php esc_html_e( 'Check Availability', 'himalayan-homestay-bookings' ); ?></button>
                <button type="submit" id="hhb-book-btn" class="hhb-btn" style="display:none"><?php esc_html_e( 'Request to Book', 'himalayan-homestay-bookings' ); ?></button>

                <!-- Payment Mode Selection -->
                <div class="hhb-payment-mode-section" id="hhb-payment-mode-section" style="display:none; margin-top:20px; border-top:1px solid rgba(0,0,0,0.08); padding-top:20px;">
                    <label class="hhb-section-label" style="margin-bottom:12px; font-size:12px;"><?php esc_html_e( 'How would you like to pay?', 'himalayan-homestay-bookings' ); ?></label>
                    <div class="hhb-payment-options" style="display:flex; flex-direction:column; gap:10px;">
                        
                        <?php 
                        $opts = get_option( \Himalayan\Homestay\Interface\Admin\SettingsPage::OPTION_KEY, [] );
                        $has_gateway = (!empty($opts['paypal_enabled']) && $opts['paypal_enabled'] === 'yes') || (!empty($opts['razorpay_enabled']) && $opts['razorpay_enabled'] === 'yes');
                        $has_cash = (!empty($opts['cash_mode_enabled']) && $opts['cash_mode_enabled'] === 'yes');
                        
                        if ( $has_gateway ) : ?>
                            <label class="hhb-payment-option" style="display:flex; align-items:flex-start; gap:12px; padding:16px; border:1px solid rgba(0,0,0,0.15); border-radius:10px; cursor:pointer; background:#fff; transition:all 0.2s;">
                                <input type="radio" name="hhb_payment_mode" value="gateway" checked style="margin-top:4px; transform:scale(1.2); accent-color:#f45c25;">
                                <div>
                                    <span style="display:block; font-weight:700; font-size:15px; margin-bottom:4px;"><?php esc_html_e( 'Pay Online Now', 'himalayan-homestay-bookings' ); ?></span>
                                    <span style="display:block; font-size:13px; color:#666; line-height:1.4;"><?php esc_html_e( 'Securely pay via Credit Card, Debit Card, or UPI to instantly confirm your booking.', 'himalayan-homestay-bookings' ); ?></span>
                                </div>
                            </label>
                        <?php endif; ?>

                        <?php if ( $has_cash ) : ?>
                            <label class="hhb-payment-option" style="display:flex; align-items:flex-start; gap:12px; padding:16px; border:1px solid rgba(0,0,0,0.15); border-radius:10px; cursor:pointer; background:#fff; transition:all 0.2s;">
                                <input type="radio" name="hhb_payment_mode" value="cash" <?php echo !$has_gateway ? 'checked' : ''; ?> style="margin-top:4px; transform:scale(1.2); accent-color:#f45c25;">
                                <div>
                                    <span style="display:block; font-weight:700; font-size:15px; margin-bottom:4px;"><?php esc_html_e( 'Pay on Arrival (Cash)', 'himalayan-homestay-bookings' ); ?></span>
                                    <span style="display:block; font-size:13px; color:#666; line-height:1.4;"><?php esc_html_e( 'Secure your dates now and pay the full amount in cash when you arrive at the homestay.', 'himalayan-homestay-bookings' ); ?></span>
                                </div>
                            </label>
                        <?php endif; ?>

                        <?php if ( !$has_gateway && !$has_cash ) : ?>
                            <div style="padding:16px; background:#fff0f0; color:#c62828; border-radius:8px; font-size:13px;">
                                <?php esc_html_e( 'No payment methods are currently available. Please contact the administrator.', 'himalayan-homestay-bookings' ); ?>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </form>

            <!-- Auth Gate Modal — shown to non-logged-in users who click "Request to Book" -->
            <div id="hhb-auth-gate-modal" class="hhb-payment-modal hhb-hidden" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Sign in to book', 'himalayan-homestay-bookings' ); ?>">
                <div class="hhb-payment-modal-content hhb-auth-modal-content">
                    <button type="button" id="hhb-auth-modal-close" aria-label="<?php esc_attr_e( 'Close', 'himalayan-homestay-bookings' ); ?>">&#x2715;</button>

                    <div class="hhb-auth-context-header">
                        <span class="hhb-auth-context-icon">🔐</span>
                        <div>
                            <p class="hhb-auth-context-title"><?php esc_html_e( 'Sign in to complete your booking', 'himalayan-homestay-bookings' ); ?></p>
                            <p class="hhb-auth-context-sub"><?php esc_html_e( 'Your selected dates will be saved.', 'himalayan-homestay-bookings' ); ?></p>
                        </div>
                    </div>

                    <div class="hhb-auth-tabs" role="tablist">
                        <button type="button" class="hhb-auth-tab hhb-auth-tab--active" data-tab="login" role="tab" aria-selected="true"><?php esc_html_e( 'Log In', 'himalayan-homestay-bookings' ); ?></button>
                        <button type="button" class="hhb-auth-tab" data-tab="register" role="tab" aria-selected="false"><?php esc_html_e( 'Create Account', 'himalayan-homestay-bookings' ); ?></button>
                    </div>

                    <div id="hhb-auth-error" class="hhb-auth-error-msg" style="display:none" role="alert"></div>

                    <div id="hhb-auth-panel-login" class="hhb-auth-panel" role="tabpanel">
                        <?php echo MyAccount::render_auth_forms_inline( 'login' ); ?>
                    </div>
                    <div id="hhb-auth-panel-register" class="hhb-auth-panel" style="display:none" role="tabpanel">
                        <?php echo MyAccount::render_auth_forms_inline( 'register' ); ?>
                    </div>
                </div>
            </div>

        </div>

        <style>
            .hhb-booking-widget { background: transparent !important; padding: 0 !important; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .hhb-widget-header h3 { margin: 0 0 8px; font-size: 24px; font-weight: 700; color: inherit; }
            .hhb-price-old { text-decoration: line-through; color: #999; font-size: 0.7em; font-weight: 400; }
            .hhb-price-current { color: inherit; }
            .hhb-price-unit { font-size: 14px; font-weight: 400; color: #666; }
            .hhb-min-stay { margin: 0 0 16px; font-size: 12px; color: #888; }
            .hhb-dates { display: flex; gap: 12px; margin-bottom: 12px; }
            .hhb-field { flex: 1; margin-bottom: 12px; }
            .hhb-field label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; color: inherit; opacity: 0.7; }
            .hhb-field input, .hhb-field select, .hhb-field textarea { width: 100%; padding: 10px 12px; border: 1px solid rgba(0,0,0,0.15); border-radius: 8px; box-sizing: border-box; background: rgba(255,255,255,0.9); color: #333; font-size: 14px; transition: border-color 0.2s; }
            .hhb-field input:focus, .hhb-field select:focus, .hhb-field textarea:focus { outline: none; border-color: #f45c25; background: #fff; box-shadow: 0 0 0 3px rgba(244,92,37,0.1); }

            /* Extras */
            .hhb-section-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; color: inherit; opacity: 0.7; }
            .hhb-extra-item { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border: 1px solid rgba(0,0,0,0.08); border-radius: 6px; margin-bottom: 4px; cursor: pointer; font-size: 13px; transition: all 0.2s; }
            .hhb-extra-item:hover { border-color: #f45c25; background: rgba(244,92,37,0.03); }
            .hhb-extra-name { flex: 1; font-weight: 500; }
            .hhb-extra-price { color: #888; font-size: 12px; }

            /* Price Breakdown */
            .hhb-price-breakdown { margin: 16px 0; padding: 16px; background: rgba(0,0,0,0.03); border-radius: 10px; }
            .hhb-line { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; color: inherit; }
            .hhb-line-label { color: #666; }
            .hhb-line-value { font-weight: 600; }
            .hhb-line-total { border-top: 2px solid rgba(0,0,0,0.1); padding-top: 10px; margin-top: 6px; font-size: 16px; }
            .hhb-line-total .hhb-line-value { color: #f45c25; font-size: 18px; }
            .hhb-deposit-line { color: #2e7d32; }
            .hhb-deposit-line .hhb-line-value { color: #2e7d32; }

            /* Customer details & Payments */
            .hhb-customer-details { margin-top: 16px; border-top: 1px solid rgba(0,0,0,0.08); padding-top: 16px; }
            .hhb-payment-option:hover { border-color: #f45c25 !important; background: rgba(244,92,37,0.02) !important; }
            .hhb-payment-option input:checked + div span:first-child { color: #f45c25; }
            .hhb-payment-option input:checked { border-color: #f45c25 !important; }

            /* Buttons */
            .hhb-btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #f45c25, #e04010); color: #fff; border: none; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 20px rgba(244,92,37,0.3); letter-spacing: 0.3px; }
            .hhb-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(244,92,37,0.4); }
            .hhb-btn:active { transform: translateY(0); }
            .hhb-btn:disabled { background: #ccc; cursor: not-allowed; transform: none; box-shadow: none; color: #666; }

            /* Messages */
            .hhb-messages { font-size: 13px; margin-bottom: 12px; padding: 10px 14px; border-radius: 8px; display: none; }
            .hhb-messages.error { background: #fff0f0; color: #cc0000; border: 1px solid #ffcccc; display: block; }
            .hhb-messages.success { background: #f0fff0; color: #006600; border: 1px solid #ccffcc; display: block; }
            
            /* Modals and Success Popups */
            .hhb-payment-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px); transition: opacity 0.3s; opacity: 0; visibility: hidden; }
            .hhb-payment-modal:not(.hhb-hidden) { opacity: 1; visibility: visible; }
            .hhb-payment-modal-content { background: #fff; border-radius: 16px; width: 90%; max-width: 440px; box-shadow: 0 20px 40px rgba(0,0,0,0.15); transform: translateY(20px); transition: transform 0.3s; box-sizing: border-box; overflow: hidden; padding: 40px 30px; text-align: center; }
            .hhb-payment-modal:not(.hhb-hidden) .hhb-payment-modal-content { transform: translateY(0); }
            
            .hhb-success-icon { display:inline-flex; align-items:center; justify-content:center; width:80px; height:80px; background:rgba(22, 163, 74, 0.1); border-radius:50%; color:#16a34a; margin-bottom:20px; }
            .hhb-success-icon svg { width:40px; height:40px; }
            .hhb-success-title { font-size:24px; font-weight:800; color:#111; margin:0 0 12px; letter-spacing:-0.5px; }
            .hhb-success-text { font-size:15px; color:#555; line-height:1.6; margin:0 0 30px; }
            .hhb-success-btn { display:inline-block; background:#111; color:#fff !important; text-decoration:none; padding:12px 30px; border-radius:30px; font-weight:600; font-size:15px; transition:all 0.2s; }
            .hhb-success-btn:hover { background:#000; transform:translateY(-1px); box-shadow:0 6px 20px rgba(0,0,0,0.15); }

            /* ── Auth Gate Modal ── */
            .hhb-auth-modal-content { max-width:420px !important; padding:0 !important; text-align:left !important; position:relative; overflow:hidden; }
            #hhb-auth-modal-close { position:absolute; top:12px; right:12px; background:rgba(0,0,0,.06); border:none; border-radius:50%; width:32px; height:32px; font-size:14px; cursor:pointer; display:flex; align-items:center; justify-content:center; z-index:2; transition:background .2s,color .2s; }
            #hhb-auth-modal-close:hover { background:#fee2e2; color:#dc2626; }
            .hhb-auth-context-header { display:flex; align-items:flex-start; gap:12px; padding:28px 24px 0; }
            .hhb-auth-context-icon { font-size:26px; line-height:1; flex-shrink:0; margin-top:2px; }
            .hhb-auth-context-title { margin:0 0 3px; font-size:16px; font-weight:800; color:#1e293b; }
            .hhb-auth-context-sub { margin:0; font-size:13px; color:#64748b; }
            .hhb-auth-tabs { display:flex; margin:20px 0 0; border-bottom:2px solid #f1f5f9; }
            .hhb-auth-tab { flex:1; padding:13px; background:none; border:none; font-size:14px; font-weight:700; cursor:pointer; color:#94a3b8; border-bottom:3px solid transparent; margin-bottom:-2px; transition:color .2s,border-color .2s; }
            .hhb-auth-tab--active { color:#f45c25; border-bottom-color:#f45c25; }
            .hhb-auth-error-msg { margin:14px 24px 0; padding:10px 14px; background:#fff0f0; color:#cc0000; border:1px solid #ffcccc; border-radius:8px; font-size:13px; line-height:1.5; }
            .hhb-auth-panel { padding:20px 24px 28px; }
            .hhb-popup-form { display:flex; flex-direction:column; }
            .hhb-popup-field { margin-bottom:14px; }
            .hhb-popup-field label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#64748b; margin-bottom:6px; }
            .hhb-popup-field input { width:100%; padding:11px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; box-sizing:border-box; outline:none; transition:border-color .2s,box-shadow .2s; background:#f8fafc; color:#1e293b; }
            .hhb-popup-field input:focus { border-color:#f45c25; background:#fff; box-shadow:0 0 0 3px rgba(244,92,37,.1); }
            .hhb-popup-submit-btn { width:100%; padding:13px; background:linear-gradient(135deg,#f45c25,#e04010); color:#fff; border:none; border-radius:12px; font-size:15px; font-weight:700; cursor:pointer; margin-top:4px; transition:opacity .2s,transform .2s; box-shadow:0 4px 15px rgba(244,92,37,.3); letter-spacing:.2px; }
            .hhb-popup-submit-btn:hover { opacity:.92; transform:translateY(-1px); }
            .hhb-popup-submit-btn:disabled { opacity:.55; cursor:not-allowed; transform:none; box-shadow:none; }
            .hhb-popup-submit-btn--dark { background:linear-gradient(135deg,#1e293b,#0f172a); box-shadow:0 4px 15px rgba(0,0,0,.2); }
        </style>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            if (typeof flatpickr === "undefined") return;

            const widget = document.querySelector(".hhb-booking-widget");
            if (!widget) return;

            const homestayId   = widget.dataset.id;
            const minNights    = parseInt(widget.dataset.minNights) || 1;
            
            const roomSelect   = document.getElementById("hhb-room-select");
            const guestSelect  = document.getElementById("hhb-guests");

            const checkInEl    = document.getElementById("hhb-check-in");
            const checkOutEl   = document.getElementById("hhb-check-out");
            const checkBtn     = document.getElementById("hhb-check-btn");
            const bookBtn      = document.getElementById("hhb-book-btn");
            const customerDiv  = document.querySelector(".hhb-customer-details");
            const breakdownDiv = document.querySelector(".hhb-price-breakdown");
            const messages     = document.getElementById("hhb-messages");
            const paymentModeSection = document.getElementById("hhb-payment-mode-section");
            const bookedDates    = JSON.parse(widget.dataset.bookedDates || "[]");
            const isLoggedIn     = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;
            const ajaxUrl        = widget.dataset.ajaxUrl;
            let   pendingPayload = null;

            roomSelect.addEventListener('change', (e) => {
                const opt = e.target.options[e.target.selectedIndex];
                const mg = parseInt(opt.dataset.guests) || 1;
                guestSelect.innerHTML = '';
                for(let i=1; i<=mg; i++) {
                    guestSelect.innerHTML += `<option value="${i}">${i} ${i===1?'Guest':'Guests'}</option>`;
                }

                const newPrice = opt.dataset.price;
                const displayPriceEl = document.getElementById('hhb-display-price-main');
                if (displayPriceEl && newPrice) {
                    const formattedPrice = Number(newPrice).toLocaleString('en-IN');
                    const cSymbol = widget.dataset.currencySymbol || '';
                    displayPriceEl.textContent = cSymbol + ' ' + formattedPrice;
                }

                resetForm();
            });

            const fpIn = flatpickr(checkInEl, {
                minDate: "today",
                dateFormat: "Y-m-d",
                disable: bookedDates,
                onChange: function(sel, dateStr) {
                    if (sel.length > 0) {
                        const minDate = new Date(sel[0].getTime() + (minNights * 86400000));
                        fpOut.set("minDate", minDate);
                        
                        // Prevent checkout from spanning across already booked dates
                        let maxDate = null;
                        for (let i = 0; i < bookedDates.length; i++) {
                            if (bookedDates[i] >= dateStr) {
                                maxDate = bookedDates[i];
                                break;
                            }
                        }
                        if (maxDate) {
                            fpOut.set("maxDate", maxDate);
                            if (checkOutEl.value && checkOutEl.value > maxDate) {
                                fpOut.clear();
                            }
                        } else {
                            fpOut.set("maxDate", null);
                        }
                    }
                    resetForm();
                }
            });
            const fpOut = flatpickr(checkOutEl, {
                minDate: new Date(new Date().getTime() + 86400000),
                dateFormat: "Y-m-d",
                onChange: resetForm
            });

            function showMsg(msg, type) {
                messages.textContent = msg;
                messages.className = "hhb-messages " + type;
            }

            function resetForm() {
                bookBtn.style.display = "none";
                checkBtn.style.display = "block";
                customerDiv.style.display = "none";
                breakdownDiv.style.display = "none";
                paymentModeSection.style.display = "none";
                document.getElementById("hhb-coupon-section").style.display = "none";
                messages.className = "hhb-messages";
            }

            function openAuthModal() {
                const m = document.getElementById('hhb-auth-gate-modal');
                if (!m) return;
                m.classList.remove('hhb-hidden');
                document.body.style.overflow = 'hidden';
            }

            function closeAuthModal() {
                const m = document.getElementById('hhb-auth-gate-modal');
                if (!m) return;
                m.classList.add('hhb-hidden');
                document.body.style.overflow = '';
                const errEl = document.getElementById('hhb-auth-error');
                if (errEl) { errEl.textContent = ''; errEl.style.display = 'none'; }
                bookBtn.textContent = '<?php esc_attr_e( 'Request to Book', 'himalayan-homestay-bookings' ); ?>';
                bookBtn.disabled = false;
            }

            function getSelectedServiceIds() {
                return Array.from(document.querySelectorAll(".hhb-service-cb:checked")).map(cb => cb.value);
            }
            
            document.getElementById("hhb-apply-coupon").addEventListener("click", () => {
                const couponInput = document.getElementById("hhb-coupon");
                if (couponInput && couponInput.value.trim() !== "") {
                    checkBtn.click(); // Re-trigger the calculation flow
                }
            });

            checkBtn.addEventListener("click", async () => {
                const ci = checkInEl.value, co = checkOutEl.value;
                const roomId = roomSelect.value;
                if (!roomId) return showMsg("Please select a room.", "error");
                if (!ci || !co) return showMsg("Please select your dates.", "error");

                checkBtn.textContent = "Checking...";
                checkBtn.disabled = true;

                try {
                    // 1. Check availability array
                    const res = await fetch("<?php echo esc_url( rest_url( 'himalayan/v1/check-availability' ) ); ?>?room_id=" + roomId + "&check_in=" + ci + "&check_out=" + co);
                    const data = await res.json();

                    if (!res.ok || !data.available) {
                        showMsg(data.message || "Dates are not available.", "error");
                        checkBtn.textContent = "Check Availability"; checkBtn.disabled = false;
                        return;
                    }

                    // 2. Calculate price with services
                    const guests     = document.getElementById("hhb-guests").value;
                    const serviceIds = getSelectedServiceIds();
                    const couponCode = document.getElementById("hhb-coupon") ? document.getElementById("hhb-coupon").value : "";
                    let priceUrl     = "<?php echo esc_url( rest_url( 'himalayan/v1/calculate-price' ) ); ?>?room_id=" + roomId + "&check_in=" + ci + "&check_out=" + co + "&guests=" + guests + "&coupon_code=" + encodeURIComponent(couponCode);
                    serviceIds.forEach(id => priceUrl += "&services[]=" + id);

                    const priceRes  = await fetch(priceUrl);
                    const priceData = await priceRes.json();

                    if (priceRes.ok) {
                        // Populate breakdown
                        const c = priceData.currency || "INR";
                        document.querySelector("#hhb-line-nightly .hhb-line-label").textContent = c + " " + priceData.base_per_night + " × " + priceData.nights + " nights";
                        document.querySelector("#hhb-line-nightly .hhb-line-value").textContent = c + " " + priceData.nightly_total;

                        const egLine = document.getElementById("hhb-line-extra-guest");
                        if (priceData.extra_guest_charge > 0) {
                            egLine.style.display = "flex";
                            egLine.querySelector(".hhb-line-value").textContent = c + " " + priceData.extra_guest_charge;
                        } else { egLine.style.display = "none"; }

                        const svcLine = document.getElementById("hhb-line-services");
                        if (priceData.services_total > 0) {
                            svcLine.style.display = "flex";
                            svcLine.querySelector(".hhb-line-value").textContent = c + " " + priceData.services_total;
                        } else { svcLine.style.display = "none"; }

                        const couponLine = document.getElementById("hhb-line-coupon");
                        if (priceData.coupon_amount > 0) {
                            couponLine.style.display = "flex";
                            couponLine.querySelector(".hhb-line-value").textContent = "- " + c + " " + priceData.coupon_amount;
                            document.getElementById("hhb-applied-coupon-name").textContent = "(" + priceData.coupon_detail.code + ")";
                        } else { 
                            couponLine.style.display = "none"; 
                            if(couponCode && couponCode.trim() !== "") {
                                showMsg("Invalid or expired coupon code.", "error");
                                setTimeout(() => { messages.textContent = ""; }, 3000);
                            }
                        }

                        document.getElementById("hhb-total-price").textContent = c + " " + priceData.grand_total;

                        const depositEl = document.getElementById("hhb-deposit-amount");
                        const balanceEl = document.getElementById("hhb-balance-due");
                        if (depositEl) depositEl.textContent = c + " " + priceData.deposit_amount;
                        if (balanceEl) balanceEl.textContent = c + " " + priceData.balance_due;

                        breakdownDiv.style.display = "block";
                        customerDiv.style.display = "block";
                        document.getElementById("hhb-coupon-section").style.display = "block";
                        paymentModeSection.style.display = "block";
                        checkBtn.style.display = "none";
                        bookBtn.style.display = "block";
                        if (!couponCode || couponCode.trim() === "" || priceData.coupon_amount > 0) {
                            showMsg("Dates are available! Complete your details below.", "success");
                        }
                    }
                } catch (e) {
                    showMsg("Network error. Please try again.", "error");
                }
                checkBtn.textContent = "Check Availability"; checkBtn.disabled = false;
            });

            document.getElementById("hhb-booking-form").addEventListener("submit", async (e) => {
                e.preventDefault();
                const roomId = roomSelect.value;
                if (!roomId) return showMsg("Please select a room.", "error");

                const payload = {
                    room_id: roomId,
                    check_in: checkInEl.value,
                    check_out: checkOutEl.value,
                    guests: document.getElementById("hhb-guests").value,
                    customer_name: document.getElementById("hhb-name").value,
                    customer_email: document.getElementById("hhb-email").value,
                    customer_phone: document.getElementById("hhb-phone").value,
                    notes: document.getElementById("hhb-notes").value,
                    services: getSelectedServiceIds(),
                    coupon_code: document.getElementById("hhb-coupon") ? document.getElementById("hhb-coupon").value : "",
                    payment_mode: document.querySelector('input[name="hhb_payment_mode"]:checked') ? document.querySelector('input[name="hhb_payment_mode"]:checked').value : ''
                };

                // Gate: require login before booking.
                if (!isLoggedIn) {
                    pendingPayload = payload;
                    openAuthModal();
                    return;
                }

                if (!payload.customer_name || !payload.customer_email) {
                    showMsg("<?php esc_attr_e( 'Name and Email are required.', 'himalayan-homestay-bookings' ); ?>", "error");
                    return;
                }

                if (!payload.payment_mode) {
                    showMsg("<?php esc_attr_e( 'Please select a payment method.', 'himalayan-homestay-bookings' ); ?>", "error");
                    return;
                }

                bookBtn.textContent = "<?php esc_attr_e( 'Processing…', 'himalayan-homestay-bookings' ); ?>"; bookBtn.disabled = true;
                processRequest(bookBtn, payload);
            });

            // processRequest is defined outside the submit handler so hhbAuthAjax can call it too.
            const processRequest = async (btn, resolvedPayload) => {
                    btn.textContent = "<?php esc_attr_e( 'Processing…', 'himalayan-homestay-bookings' ); ?>"; btn.disabled = true;
                    try {
                        const res = await fetch("<?php echo esc_url( rest_url( 'himalayan/v1/create-booking' ) ); ?>", {
                            method: "POST",
                            headers: {"Content-Type": "application/json"},
                            body: JSON.stringify(resolvedPayload)
                        });
                        const data = await res.json();

                        if (res.ok && data.booking_id) {
                            if (data.mode === 'cash') {
                                document.body.insertAdjacentHTML('beforeend', `
                                    <div id="hhb-cash-success-modal" class="hhb-payment-modal">
                                        <div class="hhb-payment-modal-content">
                                            <div class="hhb-success-icon">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                            </div>
                                            <h3 class="hhb-success-title">Thank you for booking!</h3>
                                            <p class="hhb-success-text">Your booking has been received and your dates are reserved. The admin will acknowledge you with an email about booking confirmation shortly.</p>
                                            <a href="#" onclick="window.location.reload(); return false;" class="hhb-success-btn">Done</a>
                                        </div>
                                    </div>
                                `);
                                setTimeout(() => document.getElementById('hhb-cash-success-modal').classList.remove('hhb-hidden'), 10);
                            } else {
                                btn.textContent = "Redirecting to Payment...";
                                window.location.href = "<?php echo esc_url( home_url( '/' ) ); ?>?hhb_confirmation=" + data.booking_id;
                            }
                        } else {
                            showMsg(data.message || "Booking failed.", "error");
                            btn.textContent = "Request to Book"; btn.disabled = false;
                        }
                    } catch (err) {
                        showMsg("An error occurred. Please try again.", "error");
                        btn.textContent = "Request to Book"; btn.disabled = false;
                    }
                };

            // ── Auth modal wiring ──────────────────────────────────────────
            document.getElementById('hhb-auth-modal-close')
                ?.addEventListener('click', closeAuthModal);

            document.getElementById('hhb-auth-gate-modal')
                ?.addEventListener('click', function(e) {
                    if (e.target === this) closeAuthModal();
                });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeAuthModal();
            });

            document.querySelectorAll('.hhb-auth-tab').forEach(function(tab) {
                tab.addEventListener('click', function() {
                    document.querySelectorAll('.hhb-auth-tab').forEach(t => {
                        t.classList.remove('hhb-auth-tab--active');
                        t.setAttribute('aria-selected', 'false');
                    });
                    this.classList.add('hhb-auth-tab--active');
                    this.setAttribute('aria-selected', 'true');
                    document.querySelectorAll('.hhb-auth-panel').forEach(p => p.style.display = 'none');
                    document.getElementById('hhb-auth-panel-' + this.dataset.tab).style.display = 'block';
                });
            });

            // ── AJAX auth ──────────────────────────────────────────────────
            async function hhbAuthAjax(action, formId) {
                const form  = document.getElementById(formId);
                if (!form) return;
                const errEl = document.getElementById('hhb-auth-error');
                const btn   = document.getElementById(
                    action === 'hhb_ajax_login' ? 'hhb-popup-login-btn' : 'hhb-popup-register-btn'
                );
                const nonce = form.querySelector('[name="hhb_auth_nonce"]').value;

                const fd = new FormData();
                fd.append('action', action);
                fd.append('hhb_auth_nonce', nonce);
                if (action === 'hhb_ajax_login') {
                    fd.append('log', form.querySelector('[name="log"]').value);
                    fd.append('pwd', form.querySelector('[name="pwd"]').value);
                    const rem = form.querySelector('[name="rememberme"]');
                    fd.append('rememberme', rem && rem.checked ? 'forever' : '');
                } else {
                    fd.append('email', form.querySelector('[name="email"]').value);
                    fd.append('reg_password', form.querySelector('[name="reg_password"]').value);
                }

                btn.disabled    = true;
                btn.textContent = '<?php esc_attr_e( 'Please wait…', 'himalayan-homestay-bookings' ); ?>';
                errEl.style.display = 'none';

                try {
                    const res  = await fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
                    const data = await res.json();

                    if (data.success) {
                        // Refresh the widget's REST nonce for the now-logged-in session.
                        widget.dataset.nonce = data.data.nonce;

                        // Pre-fill customer fields from user account if still empty.
                        if (pendingPayload) {
                            if (!pendingPayload.customer_name && data.data.name) {
                                pendingPayload.customer_name = data.data.name;
                                const nameEl = document.getElementById('hhb-name');
                                if (nameEl && !nameEl.value) nameEl.value = data.data.name;
                            }
                            if (!pendingPayload.customer_email && data.data.email) {
                                pendingPayload.customer_email = data.data.email;
                                const emailEl = document.getElementById('hhb-email');
                                if (emailEl && !emailEl.value) emailEl.value = data.data.email;
                            }
                        }

                        closeAuthModal();

                        if (pendingPayload) {
                            const captured = pendingPayload;
                            pendingPayload = null;

                            // Validate required fields before auto-submitting.
                            if (!captured.customer_name || !captured.customer_email) {
                                showMsg('<?php esc_attr_e( 'Please fill in your name and email to complete the booking.', 'himalayan-homestay-bookings' ); ?>', 'error');
                                return;
                            }
                            if (!captured.payment_mode) {
                                showMsg('<?php esc_attr_e( 'Please select a payment method.', 'himalayan-homestay-bookings' ); ?>', 'error');
                                return;
                            }

                            processRequest(bookBtn, captured);
                        }
                    } else {
                        const msg = (data.data && data.data.message)
                            ? data.data.message
                            : '<?php esc_attr_e( 'Authentication failed. Please try again.', 'himalayan-homestay-bookings' ); ?>';
                        errEl.textContent   = msg;
                        errEl.style.display = 'block';
                        btn.disabled        = false;
                        btn.textContent     = action === 'hhb_ajax_login'
                            ? '<?php esc_attr_e( 'Log in', 'himalayan-homestay-bookings' ); ?>'
                            : '<?php esc_attr_e( 'Create Account', 'himalayan-homestay-bookings' ); ?>';
                    }
                } catch (err) {
                    errEl.textContent   = '<?php esc_attr_e( 'Network error. Please try again.', 'himalayan-homestay-bookings' ); ?>';
                    errEl.style.display = 'block';
                    btn.disabled        = false;
                    btn.textContent     = action === 'hhb_ajax_login'
                        ? '<?php esc_attr_e( 'Log in', 'himalayan-homestay-bookings' ); ?>'
                        : '<?php esc_attr_e( 'Create Account', 'himalayan-homestay-bookings' ); ?>';
                }
            }

            document.getElementById('hhb-popup-login-btn')
                ?.addEventListener('click', () => hhbAuthAjax('hhb_ajax_login', 'hhb-popup-login-form'));
            document.getElementById('hhb-popup-register-btn')
                ?.addEventListener('click', () => hhbAuthAjax('hhb_ajax_register', 'hhb-popup-register-form'));

        });
        </script>
        <?php
        return ob_get_clean();
    }
}
