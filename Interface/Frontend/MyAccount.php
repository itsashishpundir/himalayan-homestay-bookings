<?php
/**
 * Frontend My Account Dashboard
 *
 * Handles the [hhb_my_account] shortcode, providing a native login/registration
 * flow and a user dashboard to view past and upcoming bookings.
 *
 * @package Himalayan\Homestay\Interface\Frontend
 */

namespace Himalayan\Homestay\Interface\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MyAccount {

    public static function init(): void {
        add_shortcode( 'hhb_my_account', [ __CLASS__, 'render_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_styles' ] );
        add_action( 'init', [ __CLASS__, 'handle_auth_actions' ] );

        // Always redirect to homepage after logout — regardless of where the logout link came from.
        add_filter( 'logout_redirect', function() {
            return home_url( '/' );
        } );
    }

    public static function enqueue_styles(): void {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'hhb_my_account' ) ) {
            $css = '
                /* Center the main theme page title and rely on Tailwind for everything else */
                .entry-header, .entry-title, .page-title, h1.entry-title { display: none !important; }
            ';
            wp_add_inline_style( 'himalayan-homestay', $css );
            wp_register_style( 'hhb-account-style', false );
            wp_enqueue_style( 'hhb-account-style' );
            wp_add_inline_style( 'hhb-account-style', $css );
        }
    }

    public static function handle_auth_actions(): void {
        // Handle Login
        if ( isset( $_POST['hhb_login'] ) && isset( $_POST['hhb_auth_nonce'] ) ) {
            if ( wp_verify_nonce( $_POST['hhb_auth_nonce'], 'hhb_auth_action' ) ) {
                $creds = [
                    'user_login'    => sanitize_user( $_POST['log'] ),
                    'user_password' => $_POST['pwd'],
                    'remember'      => isset( $_POST['rememberme'] ),
                ];
                $user = wp_signon( $creds, is_ssl() );
                if ( is_wp_error( $user ) ) {
                    set_transient( 'hhb_auth_error', $user->get_error_message(), 30 );
                } else {
                    wp_redirect( remove_query_arg( [ 'registered' ] ) );
                    exit;
                }
            }
        }

        // Handle Registration
        if ( isset( $_POST['hhb_register'] ) && isset( $_POST['hhb_auth_nonce'] ) ) {
            if ( wp_verify_nonce( $_POST['hhb_auth_nonce'], 'hhb_auth_action' ) ) {
                $email    = sanitize_email( $_POST['email'] ?? '' );
                $password = $_POST['reg_password'] ?? '';

                if ( ! is_email( $email ) ) {
                    set_transient( 'hhb_auth_error', __( 'Invalid email address.', 'himalayan-homestay-bookings' ), 30 );
                } elseif ( empty( $password ) ) {
                    set_transient( 'hhb_auth_error', __( 'Please provide a password.', 'himalayan-homestay-bookings' ), 30 );
                } elseif ( email_exists( $email ) ) {
                    set_transient( 'hhb_auth_error', __( 'An account with this email already exists.', 'himalayan-homestay-bookings' ), 30 );
                } else {
                    $user_id  = wp_create_user( $email, $password, $email );
                    if ( is_wp_error( $user_id ) ) {
                        set_transient( 'hhb_auth_error', $user_id->get_error_message(), 30 );
                    } else {
                        // Assign the Guest role (not default subscriber)
                        $user_obj = new \WP_User( $user_id );
                        $user_obj->set_role( 'hhb_guest' );

                        // Auto-login
                        wp_set_auth_cookie( $user_id, true );
                        wp_redirect( remove_query_arg( [ 'registered' ] ) );
                        exit;
                    }
                }
            }
        }

        // ── Guest Self-Service Cancellation ──
        if ( isset( $_POST['hhb_guest_cancel'] ) && isset( $_POST['hhb_cancel_nonce'] ) ) {
            if ( ! is_user_logged_in() || ! wp_verify_nonce( $_POST['hhb_cancel_nonce'], 'hhb_guest_cancel_action' ) ) {
                return;
            }

            $booking_id = intval( $_POST['hhb_cancel_booking_id'] ?? 0 );
            if ( ! $booking_id ) return;

            global $wpdb;
            $table = $wpdb->prefix . 'himalayan_bookings';
            $user  = wp_get_current_user();

            $booking_obj = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND customer_email = %s",
                $booking_id, $user->user_email
            ) );

            if ( ! $booking_obj ) {
                set_transient( 'hhb_auth_error', __( 'Booking not found or does not belong to you.', 'himalayan-homestay-bookings' ), 30 );
                return;
            }
            if ( $booking_obj->status !== 'confirmed' ) {
                set_transient( 'hhb_auth_error', __( 'Only confirmed bookings can be cancelled.', 'himalayan-homestay-bookings' ), 30 );
                return;
            }
            if ( time() >= strtotime( $booking_obj->check_in ) ) {
                set_transient( 'hhb_auth_error', __( 'Cannot cancel a booking after the check-in date.', 'himalayan-homestay-bookings' ), 30 );
                return;
            }

            try {
                // Calculate refund using the same policy engine as admin.
                $opts   = get_option( \Himalayan\Homestay\Interface\Admin\SettingsPage::OPTION_KEY, [] );
                $policy = $opts['cancellation_policy'] ?? 'flexible';
                $hours_until = ( strtotime( $booking_obj->check_in ) - time() ) / 3600;
                $refund_pct  = 0;

                switch ( $policy ) {
                    case 'flexible':
                        $min_hours  = intval( $opts['cancellation_flexible_hours'] ?? 24 );
                        $refund_pct = ( $hours_until >= $min_hours ) ? 100 : 0;
                        break;
                    case 'moderate':
                        $min_days   = intval( $opts['cancellation_moderate_days'] ?? 3 );
                        $refund_pct = ( $hours_until >= ( $min_days * 24 ) ) ? 50 : 0;
                        break;
                    case 'strict':
                        $refund_pct = 0;
                        break;
                    case 'custom':
                        $refund_pct = max( 0, min( 100, intval( $opts['cancellation_custom_pct'] ?? 100 ) ) );
                        break;
                }

                $refund_id     = '';
                $refund_amount = 0;

                if ( ! empty( $booking_obj->transaction_id ) && $booking_obj->gateway !== 'cash' && $refund_pct > 0 ) {
                    $refund_paise = (int) round( (float) $booking_obj->total_price * 100 * ( $refund_pct / 100 ) );
                    $gateway = new \Himalayan\Homestay\Infrastructure\Payments\RazorpayGateway();
                    $result  = $gateway->refund( $booking_obj->transaction_id, $refund_paise );

                    if ( ! empty( $result['error'] ) ) {
                        set_transient( 'hhb_auth_error', __( 'Refund failed. Please contact support.', 'himalayan-homestay-bookings' ), 30 );
                        error_log( 'HHB Guest Cancel Refund Error: ' . $result['error'] );
                        return;
                    }

                    $refund_id     = $result['id'] ?? '';
                    $refund_amount = $result['amount'] ?? 0;
                }

                $manager = new \Himalayan\Homestay\Domain\Booking\BookingManager();
                $manager->refund_booking( $booking_id, $refund_id, (float) $refund_amount );

                $msg = $refund_pct > 0
                    ? sprintf( __( 'Booking #%d cancelled. %d%% refund of \u20b9%s will be credited to your account within 5-7 business days.', 'himalayan-homestay-bookings' ), $booking_id, $refund_pct, number_format( (float) $refund_amount / 100, 2 ) )
                    : sprintf( __( 'Booking #%d cancelled. No refund is applicable under the current cancellation policy.', 'himalayan-homestay-bookings' ), $booking_id );

                set_transient( 'hhb_auth_success', $msg, 30 );

            } catch ( \Exception $e ) {
                error_log( 'HHB Guest Cancel Exception: ' . $e->getMessage() );
                set_transient( 'hhb_auth_error', __( 'An error occurred. Please try again or contact support.', 'himalayan-homestay-bookings' ), 30 );
            }
        }
    }

    public static function render_shortcode(): string {
        ob_start();
        echo '<div class="hhb-account-wrap">';

        if ( is_user_logged_in() ) {
            self::render_dashboard();
        } else {
            self::render_auth_forms();
        }

        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Returns standalone login OR register form HTML for embedding in a popup.
     * Buttons are type=button (no page-POST). Form has a unique ID.
     *
     * @param string $panel 'login' or 'register'
     */
    public static function render_auth_forms_inline( string $panel ): string {
        $auth_nonce = wp_create_nonce( 'hhb_auth_action' );
        ob_start();

        if ( 'login' === $panel ) : ?>
            <form id="hhb-popup-login-form" class="hhb-popup-form" autocomplete="on">
                <input type="hidden" name="hhb_auth_nonce" value="<?php echo esc_attr( $auth_nonce ); ?>">
                <div class="hhb-popup-field">
                    <label for="hhb-popup-log"><?php esc_html_e( 'Username or email', 'himalayan-homestay-bookings' ); ?></label>
                    <input type="text" name="log" id="hhb-popup-log" required autocomplete="username" placeholder="you@example.com">
                </div>
                <div class="hhb-popup-field">
                    <label for="hhb-popup-pwd"><?php esc_html_e( 'Password', 'himalayan-homestay-bookings' ); ?></label>
                    <input type="password" name="pwd" id="hhb-popup-pwd" required autocomplete="current-password" placeholder="••••••••">
                </div>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:18px;">
                    <input type="checkbox" name="rememberme" id="hhb-popup-remember" value="forever" style="width:auto;margin:0;cursor:pointer;">
                    <label for="hhb-popup-remember" style="font-size:13px;color:#64748b;margin:0;cursor:pointer;font-weight:500;"><?php esc_html_e( 'Remember me', 'himalayan-homestay-bookings' ); ?></label>
                </div>
                <button type="button" id="hhb-popup-login-btn" class="hhb-popup-submit-btn">
                    <?php esc_html_e( 'Log in', 'himalayan-homestay-bookings' ); ?>
                </button>
            </form>
        <?php elseif ( 'register' === $panel ) : ?>
            <form id="hhb-popup-register-form" class="hhb-popup-form" autocomplete="on">
                <input type="hidden" name="hhb_auth_nonce" value="<?php echo esc_attr( $auth_nonce ); ?>">
                <div class="hhb-popup-field">
                    <label for="hhb-popup-reg-email"><?php esc_html_e( 'Email address', 'himalayan-homestay-bookings' ); ?></label>
                    <input type="email" name="email" id="hhb-popup-reg-email" required autocomplete="email" placeholder="you@example.com">
                </div>
                <div class="hhb-popup-field">
                    <label for="hhb-popup-reg-pwd"><?php esc_html_e( 'Password', 'himalayan-homestay-bookings' ); ?></label>
                    <input type="password" name="reg_password" id="hhb-popup-reg-pwd" required autocomplete="new-password" placeholder="Create a password">
                </div>
                <button type="button" id="hhb-popup-register-btn" class="hhb-popup-submit-btn">
                    <?php esc_html_e( 'Create Account', 'himalayan-homestay-bookings' ); ?>
                </button>
            </form>
        <?php endif;

        return ob_get_clean();
    }

    private static function render_auth_forms(): void {
        $error = get_transient( 'hhb_auth_error' );
        if ( $error ) {
            echo '<div class="max-w-5xl mx-auto mb-6 px-5 py-4 bg-red-50 text-red-700 border border-red-200 rounded-xl text-center font-medium shadow-sm">' . wp_kses_post( $error ) . '</div>';
            delete_transient( 'hhb_auth_error' );
        }
        $success = get_transient( 'hhb_auth_success' );
        if ( $success ) {
            echo '<div class="max-w-5xl mx-auto mb-6 px-5 py-4 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl text-center font-medium shadow-sm">' . wp_kses_post( $success ) . '</div>';
            delete_transient( 'hhb_auth_success' );
        }
        ?>
        <div class="max-w-5xl mx-auto py-12 px-4">
            <div class="grid lg:grid-cols-2 gap-12">
                
                <!-- Login Form -->
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl p-8 md:p-10 rounded-3xl shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-800">
                    <h2 class="text-3xl font-extrabold text-slate-900 dark:text-white mb-8"><?php esc_html_e( 'Welcome Back', 'himalayan-homestay-bookings' ); ?></h2>
                    <form method="post" class="space-y-6">
                        <?php wp_nonce_field( 'hhb_auth_action', 'hhb_auth_nonce' ); ?>
                        <div>
                            <label for="hhb-log" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2"><?php esc_html_e( 'Username or email address', 'himalayan-homestay-bookings' ); ?></label>
                            <input type="text" name="log" id="hhb-log" required class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 py-3.5 focus:ring-2 focus:ring-primary/20 transition-all outline-none text-slate-900 dark:text-white font-medium">
                        </div>
                        <div>
                            <label for="hhb-pwd" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2"><?php esc_html_e( 'Password', 'himalayan-homestay-bookings' ); ?></label>
                            <input type="password" name="pwd" id="hhb-pwd" required class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 py-3.5 focus:ring-2 focus:ring-primary/20 transition-all outline-none text-slate-900 dark:text-white font-medium">
                        </div>
                        <div class="flex items-center gap-2 pt-2">
                            <input type="checkbox" name="rememberme" id="hhb-rememberme" value="forever" class="rounded border-slate-300 text-primary focus:ring-primary size-4">
                            <label for="hhb-rememberme" class="text-sm font-medium text-slate-600 dark:text-slate-400 cursor-pointer"><?php esc_html_e( 'Remember me', 'himalayan-homestay-bookings' ); ?></label>
                        </div>
                        <button type="submit" name="hhb_login" class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-3.5 px-6 rounded-full transition-transform hover:scale-[1.02] active:scale-95 shadow-lg shadow-primary/20 flex items-center justify-center gap-2">
                            <?php esc_html_e( 'Log in', 'himalayan-homestay-bookings' ); ?> <span class="material-symbols-outlined text-[20px]">login</span>
                        </button>
                    </form>
                </div>

                <!-- Register Form -->
                <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl p-8 md:p-10 rounded-3xl shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-800 relative overflow-hidden">
                    <div class="absolute -top-24 -right-24 w-64 h-64 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>
                    
                    <h2 class="text-3xl font-extrabold text-slate-900 dark:text-white mb-4 relative z-10"><?php esc_html_e( 'Create an Account', 'himalayan-homestay-bookings' ); ?></h2>
                    <p class="text-slate-500 dark:text-slate-400 mb-8 leading-relaxed font-medium relative z-10">
                        <?php esc_html_e( 'Join us to track and manage your homestay bookings securely and seamlessly.', 'himalayan-homestay-bookings' ); ?>
                    </p>
                    
                    <form method="post" class="space-y-6 relative z-10">
                        <?php wp_nonce_field( 'hhb_auth_action', 'hhb_auth_nonce' ); ?>
                        <div>
                            <label for="hhb-reg-email" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2"><?php esc_html_e( 'Email address', 'himalayan-homestay-bookings' ); ?></label>
                            <input type="email" name="email" id="hhb-reg-email" required class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 py-3.5 focus:ring-2 focus:ring-primary/20 transition-all outline-none text-slate-900 dark:text-white font-medium">
                        </div>
                        <div>
                            <label for="hhb-reg-pwd" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2"><?php esc_html_e( 'Password', 'himalayan-homestay-bookings' ); ?></label>
                            <input type="password" name="reg_password" id="hhb-reg-pwd" required class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 py-3.5 focus:ring-2 focus:ring-primary/20 transition-all outline-none text-slate-900 dark:text-white font-medium">
                        </div>
                        <button type="submit" name="hhb_register" class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-3.5 px-6 rounded-full transition-transform hover:scale-[1.02] active:scale-95 shadow-lg shadow-primary/20 flex items-center justify-center gap-2">
                            <?php esc_html_e( 'Register', 'himalayan-homestay-bookings' ); ?> <span class="material-symbols-outlined text-[20px]">person_add</span>
                        </button>
                    </form>
                </div>

            </div>
        </div>
        <?php
    }

    private static function render_dashboard(): void {
        $user = wp_get_current_user();
        
        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_bookings';
        // Get bookings matching this user's email
        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE customer_email = %s ORDER BY created_at DESC",
            $user->user_email
        ), ARRAY_A );

        // Helper for Avatar
        $custom_avatar_id = get_user_meta( $user->ID, 'hhb_avatar_id', true );
        $avatar_url = $custom_avatar_id ? wp_get_attachment_image_url( $custom_avatar_id, 'medium' ) : get_avatar_url( $user->ID, ['size' => 150] );

        ?>
        <div class="max-w-7xl mx-auto mt-12 py-12 px-4 border-t border-slate-100">
            <div class="grid lg:grid-cols-4 gap-8">
                
                <!-- Sidebar -->
                <div class="lg:col-span-1">
                    <div class="bg-white dark:bg-slate-900 rounded-[2rem] p-8 shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-800 text-center sticky top-24 relative overflow-hidden">
                        
                        <!-- Top decorative background -->
                        <div class="absolute top-0 left-0 right-0 h-24 bg-gradient-to-br from-primary/10 to-primary/5"></div>
                        
                        <div class="w-32 h-32 mx-auto rounded-full overflow-hidden border-4 border-white dark:border-slate-900 shadow-xl mb-4 relative z-10 bg-slate-100">
                            <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($user->display_name); ?>" class="w-full h-full object-cover">
                        </div>
                        <h2 class="text-xl font-extrabold text-slate-900 dark:text-white mb-1 relative z-10"><?php echo esc_html( $user->display_name ); ?></h2>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-8 relative z-10"><?php echo esc_html( $user->user_email ); ?></p>
                        
                        <div class="space-y-3 relative z-10">
                            <?php if ( in_array( 'hhb_host', (array) $user->roles ) || in_array( 'administrator', (array) $user->roles ) ) : ?>
                            <a href="<?php echo esc_url( home_url( '/host-dashboard/' ) ); ?>" class="flex items-center justify-center gap-2 w-full bg-primary/10 text-primary hover:bg-primary hover:text-white font-bold py-3 px-4 rounded-xl transition-colors">
                                <span class="material-symbols-outlined text-[20px]">dashboard</span> Host Dashboard
                            </a>
                            <?php endif; ?>
                            <a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>" class="flex items-center justify-center gap-2 w-full bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-red-50 hover:text-red-600 font-bold py-3 px-4 rounded-xl transition-colors">
                                <span class="material-symbols-outlined text-[20px]">logout</span> <?php esc_html_e( 'Log out', 'himalayan-homestay-bookings' ); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="lg:col-span-3 space-y-12">
                    
                    <!-- Bookings Section -->
                    <section>
                        <h3 class="text-2xl font-extrabold text-slate-900 dark:text-white mb-6 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">luggage</span>
                            <?php esc_html_e( 'Your Bookings', 'himalayan-homestay-bookings' ); ?>
                        </h3>

                        <?php if ( empty( $bookings ) ) : ?>
                            <div class="bg-white/50 dark:bg-slate-900/50 backdrop-blur-sm rounded-3xl p-12 text-center border border-slate-200 border-dashed dark:border-slate-800">
                                <span class="material-symbols-outlined text-5xl text-slate-300 dark:text-slate-600 mb-4 block">event_available</span>
                                <h4 class="text-lg font-bold text-slate-700 dark:text-slate-300 mb-2"><?php esc_html_e( 'No bookings yet', 'himalayan-homestay-bookings' ); ?></h4>
                                <p class="text-slate-500 dark:text-slate-400 mb-6 max-w-sm mx-auto font-medium"><?php esc_html_e( 'You have not made any bookings yet. Start exploring properties for your next adventure!', 'himalayan-homestay-bookings' ); ?></p>
                                <a href="<?php echo esc_url( home_url( '/homestays/' ) ); ?>" class="inline-block bg-primary text-white font-bold px-8 py-3.5 rounded-full hover:scale-105 transition-transform shadow-lg shadow-primary/20"><?php esc_html_e( 'Explore Homestays', 'himalayan-homestay-bookings' ); ?></a>
                            </div>
                        <?php else : ?>
                            <div class="space-y-6">
                                <?php foreach ( $bookings as $b ) : 
                                    $homestay = get_post( $b['homestay_id'] );
                                    $title    = $homestay ? $homestay->post_title : 'Homestay';
                                    $fmt      = get_option( 'date_format' );
                                    $checkin  = date_i18n( $fmt, strtotime( $b['check_in'] ) );
                                    $checkout = date_i18n( $fmt, strtotime( $b['check_out'] ) );
                                    $nights   = (int) ( ( strtotime( $b['check_out'] ) - strtotime( $b['check_in'] ) ) / DAY_IN_SECONDS );
                                    $thumb_url = has_post_thumbnail( $b['homestay_id'] ) ? get_the_post_thumbnail_url( $b['homestay_id'], 'medium' ) : '';
                                    
                                    // Status styling
                                    $status_styles = [
                                        'pending_inquiry' => 'bg-amber-100 text-amber-800 border-amber-200',
                                        'approved'        => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                        'confirmed'       => 'bg-blue-100 text-blue-800 border-blue-200',
                                        'cancelled'       => 'bg-red-100 text-red-800 border-red-200',
                                        'refunded'        => 'bg-pink-100 text-pink-800 border-pink-200',
                                        'payment_expired' => 'bg-slate-100 text-slate-700 border-slate-200',
                                    ];
                                    $status_icons = [
                                        'pending_inquiry' => 'schedule',
                                        'approved'        => 'check_circle',
                                        'confirmed'       => 'verified',
                                        'cancelled'       => 'cancel',
                                        'refunded'        => 'currency_rupee',
                                        'payment_expired' => 'history',
                                    ];
                                    $s_class = $status_styles[ $b['status'] ] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                                    $s_icon = $status_icons[ $b['status'] ] ?? 'info';
                                ?>
                                    <div class="group bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 hover:shadow-xl hover:shadow-slate-200/40 hover:border-primary/30 transition-all flex flex-col md:flex-row gap-6 items-start md:items-center">
                                        
                                        <?php if ( $thumb_url ) : ?>
                                        <div class="w-full md:w-48 h-36 shrink-0 rounded-2xl overflow-hidden bg-slate-100 relative">
                                            <img src="<?php echo esc_url($thumb_url); ?>" alt="" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                                        </div>
                                        <?php endif; ?>

                                        <div class="flex-1 min-w-0 w-full">
                                            <div class="flex flex-wrap items-start justify-between gap-4 mb-3">
                                                <h4 class="text-xl font-extrabold text-slate-900 dark:text-white truncate"><?php echo esc_html( $title ); ?></h4>
                                                <span class="shrink-0 px-3 py-1.5 rounded-xl text-xs font-bold uppercase tracking-wide border flex items-center gap-1.5 <?php echo esc_attr($s_class); ?>">
                                                    <span class="material-symbols-outlined text-[16px]"><?php echo esc_attr($s_icon); ?></span>    
                                                    <?php echo esc_html( str_replace( '_', ' ', $b['status'] ) ); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="flex flex-wrap gap-x-6 gap-y-3 text-sm font-semibold text-slate-500 dark:text-slate-400 mb-6">
                                                <div class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-800 px-3 py-1.5 rounded-lg"><span class="material-symbols-outlined text-[18px]">calendar_month</span> <?php echo esc_html( $checkin . ' — ' . $checkout ); ?></div>
                                                <div class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-800 px-3 py-1.5 rounded-lg"><span class="material-symbols-outlined text-[18px]">group</span> <?php echo esc_html( $b['guests'] ); ?> Guests</div>
                                                <div class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-800 px-3 py-1.5 rounded-lg"><span class="material-symbols-outlined text-[18px]">tag</span> Ref: #<?php echo esc_html( $b['id'] ); ?></div>
                                            </div>

                                            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 dark:border-slate-800 pt-5">
                                                <div class="text-2xl font-black text-slate-900 dark:text-white flex items-center gap-1">
                                                    <span class="text-slate-400 text-lg font-bold mr-1">₹</span><?php echo esc_html( number_format( (float) $b['total_price'] ) ); ?>
                                                </div>
                                                
                                                <div class="flex items-center gap-3">
                                                    <a href="<?php echo esc_url( add_query_arg( 'hhb_download_invoice', $b['id'], home_url( '/' ) ) ); ?>" class="text-sm font-bold text-primary bg-primary/10 hover:bg-primary hover:text-white px-4 py-2.5 rounded-xl transition-colors flex items-center gap-1.5">
                                                        <span class="material-symbols-outlined text-[18px]">receipt_long</span> Invoice
                                                    </a>
                                                    
                                                    <?php
                                                    if ( $b['status'] === 'confirmed' && strtotime( $b['check_in'] ) > time() ) :
                                                        $opts   = get_option( \Himalayan\Homestay\Interface\Admin\SettingsPage::OPTION_KEY, [] );
                                                        $policy = $opts['cancellation_policy'] ?? 'flexible';
                                                        $hours  = ( strtotime( $b['check_in'] ) - time() ) / 3600;
                                                        $pct    = 0;
                                                        switch ( $policy ) {
                                                            case 'flexible': $pct = ( $hours >= intval( $opts['cancellation_flexible_hours'] ?? 24 ) ) ? 100 : 0; break;
                                                            case 'moderate': $pct = ( $hours >= ( intval( $opts['cancellation_moderate_days'] ?? 3 ) * 24 ) ) ? 50 : 0; break;
                                                            case 'strict':   $pct = 0; break;
                                                            case 'custom':   $pct = max( 0, min( 100, intval( $opts['cancellation_custom_pct'] ?? 100 ) ) ); break;
                                                        }
                                                        $refund_display = $pct > 0
                                                            ? sprintf( __( '%d%% refund (₹%s)', 'himalayan-homestay-bookings' ), $pct, number_format( (float) $b['total_price'] * $pct / 100 ) )
                                                            : __( 'No refund', 'himalayan-homestay-bookings' );
                                                    ?>
                                                        <form method="post" class="m-0 p-0" onsubmit="return confirm('Are you sure you want to cancel this booking?\n\nRefund: <?php echo esc_attr( $refund_display ); ?>');">
                                                            <?php wp_nonce_field( 'hhb_guest_cancel_action', 'hhb_cancel_nonce' ); ?>
                                                            <input type="hidden" name="hhb_cancel_booking_id" value="<?php echo intval( $b['id'] ); ?>" />
                                                            <button type="submit" name="hhb_guest_cancel" class="text-sm font-bold text-red-500 hover:text-white hover:bg-red-500 px-4 py-2.5 rounded-xl transition-colors border border-red-200 bg-red-50 flex items-center gap-1.5">
                                                                <span class="material-symbols-outlined text-[18px]">free_cancellation</span> Cancel
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <!-- Wishlist Section -->
                    <section class="mt-16">
                        <h3 class="text-2xl font-extrabold text-slate-900 dark:text-white mb-6 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary" style="font-variation-settings:'FILL' 1">favorite</span>
                            <?php esc_html_e( 'Saved Properties', 'himalayan-homestay-bookings' ); ?>
                        </h3>

                        <?php
                        $wishlist = get_user_meta( $user->ID, 'hhb_wishlist', true );
                        if ( empty( $wishlist ) || ! is_array( $wishlist ) ) : ?>
                            <div class="bg-white/50 dark:bg-slate-900/50 backdrop-blur-sm rounded-3xl p-12 text-center border border-slate-200 border-dashed dark:border-slate-800">
                                <span class="material-symbols-outlined text-5xl text-slate-300 dark:text-slate-600 mb-4 block">heart_broken</span>
                                <h4 class="text-lg font-bold text-slate-700 dark:text-slate-300 mb-2">No saved properties yet</h4>
                                <p class="text-slate-500 dark:text-slate-400 mb-6 max-w-sm mx-auto font-medium">Click the ♥ heart icon on any homestay to save it here for later.</p>
                                <a href="<?php echo esc_url( get_post_type_archive_link('hhb_homestay') ); ?>" class="inline-block bg-primary text-white font-bold px-8 py-3.5 rounded-full hover:scale-105 transition-transform shadow-lg shadow-primary/20">Browse Stays</a>
                            </div>
                        <?php else :
                            $wishlist_query = new \WP_Query([
                                'post_type'      => 'hhb_homestay',
                                'post__in'       => $wishlist,
                                'posts_per_page' => -1,
                                'orderby'        => 'post__in',
                            ]);
                            global $wpdb;
                            $hhb_rtable = $wpdb->prefix . 'hhb_reviews';
                            $hhb_rexist = $wpdb->get_var( "SHOW TABLES LIKE '{$hhb_rtable}'" );

                            if ( $wishlist_query->have_posts() ) : ?>
                                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6" id="hhb-wishlist-grid">
                                    <?php while ( $wishlist_query->have_posts() ) : $wishlist_query->the_post();
                                        $wl_id       = get_the_ID();
                                        $price_range = hhb_get_price_range( $wl_id );
                                        $locs        = get_the_terms( $wl_id, 'hhb_location' );
                                        $loc_name    = ( $locs && ! is_wp_error( $locs ) ) ? $locs[0]->name : '';
                                        $types       = get_the_terms( $wl_id, 'hhb_property_type' );
                                        $type_name   = ( $types && ! is_wp_error( $types ) ) ? $types[0]->name : '';
                                        $gallery_ids = get_post_meta( $wl_id, 'hhb_gallery', true );
                                        $gallery_ids = $gallery_ids ? explode( ',', $gallery_ids ) : [];
                                        $thumb_url   = ! empty( $gallery_ids ) ? wp_get_attachment_image_url( $gallery_ids[0], 'medium_large' ) : get_the_post_thumbnail_url( $wl_id, 'medium_large' );
                                        $wl_rating = 0; $wl_rcount = 0;
                                        if ( $hhb_rexist ) {
                                            $rrow = $wpdb->get_row( $wpdb->prepare( "SELECT AVG(rating) AS avg_r, COUNT(*) AS cnt FROM {$hhb_rtable} WHERE homestay_id = %d AND status = 'approved'", $wl_id ) );
                                            if ( $rrow && $rrow->cnt ) { $wl_rating = round( (float)$rrow->avg_r, 1 ); $wl_rcount = (int)$rrow->cnt; }
                                        }
                                    ?>
                                    <div class="group bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col" data-wishlist-card="<?php echo esc_attr($wl_id); ?>">

                                        <!-- Thumbnail -->
                                        <div class="relative aspect-[4/3] overflow-hidden bg-slate-100">
                                            <?php if ( $thumb_url ) : ?>
                                                <a href="<?php the_permalink(); ?>">
                                                    <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                                                </a>
                                            <?php else : ?>
                                                <a href="<?php the_permalink(); ?>" class="flex items-center justify-center h-full">
                                                    <span class="material-symbols-outlined text-5xl text-slate-300">landscape</span>
                                                </a>
                                            <?php endif; ?>

                                            <?php if ( $type_name ) : ?>
                                            <span class="absolute top-3 left-3 bg-white/90 backdrop-blur px-2.5 py-1 rounded-full text-[10px] font-black text-slate-700 uppercase tracking-widest shadow-sm pointer-events-none">
                                                <?php echo esc_html($type_name); ?>
                                            </span>
                                            <?php endif; ?>

                                            <!-- Remove from wishlist button -->
                                            <button type="button"
                                                class="hhb-wl-remove absolute top-3 right-3 w-9 h-9 rounded-full bg-white/90 backdrop-blur shadow flex items-center justify-center text-red-400 hover:text-white hover:bg-red-500 transition-all z-10"
                                                data-post-id="<?php echo esc_attr($wl_id); ?>"
                                                title="Remove from wishlist"
                                                aria-label="Remove from wishlist">
                                                <svg class="w-5 h-5 pointer-events-none" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                                </svg>
                                            </button>
                                        </div>

                                        <!-- Card Body -->
                                        <div class="p-4 flex flex-col gap-2 flex-1">
                                            <a href="<?php the_permalink(); ?>" class="text-base font-bold text-slate-900 dark:text-white hover:text-primary transition-colors line-clamp-2 leading-snug">
                                                <?php the_title(); ?>
                                            </a>

                                            <?php if ( $loc_name ) : ?>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[14px] text-primary">location_on</span>
                                                <?php echo esc_html($loc_name); ?>
                                            </p>
                                            <?php endif; ?>

                                            <div class="flex items-center justify-between mt-auto pt-3 border-t border-slate-50 dark:border-slate-800">
                                                <div>
                                                    <?php if ( $price_range ) : ?>
                                                        <span class="text-primary font-black text-lg"><?php echo esc_html($price_range['formatted']); ?></span>
                                                        <span class="text-slate-400 text-[11px] font-bold"> / night</span>
                                                    <?php else : ?>
                                                        <span class="text-slate-400 text-sm font-medium">Price TBD</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex items-center gap-1 text-xs font-semibold text-slate-600 dark:text-slate-400">
                                                    <span class="material-symbols-outlined text-yellow-400 text-[15px]" style="font-variation-settings:'FILL' 1">star</span>
                                                    <?php if ( $wl_rcount > 0 ) : ?>
                                                        <?php echo esc_html($wl_rating); ?> <span class="text-slate-400">(<?php echo esc_html($wl_rcount); ?>)</span>
                                                    <?php else : ?>
                                                        <span class="text-slate-400">New</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <a href="<?php the_permalink(); ?>" class="mt-2 block w-full text-center text-sm font-bold bg-slate-50 dark:bg-slate-800 hover:bg-primary hover:text-white text-slate-700 dark:text-slate-200 py-2.5 rounded-xl transition-all">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                    <?php endwhile; wp_reset_postdata(); ?>
                                </div>

                                <!-- Browse more CTA -->
                                <div class="mt-8 text-center">
                                    <a href="<?php echo esc_url( get_post_type_archive_link('hhb_homestay') ); ?>" class="inline-flex items-center gap-2 text-sm font-bold text-primary hover:underline">
                                        <span class="material-symbols-outlined text-[18px]">explore</span>
                                        Browse more stays
                                    </a>
                                </div>

                                <!-- AJAX remove handler -->
                                <script>
                                (function() {
                                    var nonce    = '<?php echo wp_create_nonce("hhb_wishlist_nonce"); ?>';
                                    var ajaxUrl  = '<?php echo esc_js( admin_url("admin-ajax.php") ); ?>';
                                    var emptyMsg = '<div class="bg-white/50 rounded-3xl p-12 text-center border border-slate-200 border-dashed"><span class="material-symbols-outlined text-5xl text-slate-300 mb-4 block">heart_broken</span><h4 class="text-lg font-bold text-slate-500 mb-2">Your wishlist is empty</h4><a href="<?php echo esc_js( get_post_type_archive_link("hhb_homestay") ); ?>" class="inline-block mt-2 bg-primary text-white font-bold px-8 py-3 rounded-full hover:scale-105 transition-transform">Browse Stays</a></div>';

                                    document.querySelectorAll('.hhb-wl-remove').forEach(function(btn) {
                                        btn.addEventListener('click', function(e) {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            var postId = this.dataset.postId;
                                            var card   = document.querySelector('[data-wishlist-card="' + postId + '"]');
                                            var fd     = new FormData();
                                            fd.append('action',   'hhb_toggle_wishlist');
                                            fd.append('post_id',  postId);
                                            fd.append('security', nonce);

                                            if (card) { card.style.opacity = '0.4'; card.style.pointerEvents = 'none'; }

                                            fetch(ajaxUrl, { method: 'POST', body: fd })
                                                .then(function(r) { return r.json(); })
                                                .then(function(data) {
                                                    if (data.success && !data.data.is_favorited) {
                                                        if (card) {
                                                            card.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
                                                            card.style.transform  = 'scale(0.88)';
                                                            card.style.opacity    = '0';
                                                            setTimeout(function() {
                                                                card.remove();
                                                                var grid = document.getElementById('hhb-wishlist-grid');
                                                                if (grid && grid.children.length === 0) {
                                                                    grid.parentNode.innerHTML = emptyMsg;
                                                                }
                                                            }, 280);
                                                        }
                                                    } else {
                                                        if (card) { card.style.opacity = '1'; card.style.pointerEvents = ''; }
                                                    }
                                                })
                                                .catch(function() {
                                                    if (card) { card.style.opacity = '1'; card.style.pointerEvents = ''; }
                                                });
                                        });
                                    });
                                })();
                                </script>

                            <?php else : ?>
                                <div class="bg-white/50 dark:bg-slate-900/50 backdrop-blur-sm rounded-3xl p-10 text-center border border-slate-200 border-dashed dark:border-slate-800">
                                    <p class="text-slate-500 dark:text-slate-400 font-medium"><?php esc_html_e( 'Some saved properties are no longer available.', 'himalayan-homestay-bookings' ); ?></p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </div>
        <?php
    }
}
