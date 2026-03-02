<?php
/**
 * Review Collection Frontend Page
 *
 * Intercepts requests with ?hhb_review=1&booking_id=<booking_id>&token=<token> and renders
 * a dedicated Review submission screen. Also handles the POST submission.
 *
 * @package Himalayan\Homestay\Interface\Frontend
 */

namespace Himalayan\Homestay\Interface\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReviewPage {

    public static function init(): void {
        add_filter( 'template_include', [ __CLASS__, 'intercept_review_template' ], 99 );
        add_action( 'template_redirect', [ __CLASS__, 'handle_post_submission' ] );
    }

    public static function handle_post_submission(): void {
        if ( isset( $_GET['hhb_review'] ) && isset( $_POST['hhb_submit_review'] ) ) {
            // CSRF protection via WP nonce
            if ( ! isset( $_POST['hhb_review_nonce'] ) || ! wp_verify_nonce( $_POST['hhb_review_nonce'], 'hhb_submit_review_action' ) ) {
                wp_die( __( 'Security check failed. Please use the link sent to your email.', 'himalayan-homestay-bookings' ) );
            }

            $booking_id = intval( $_POST['booking_id'] );
            $token      = sanitize_text_field( $_POST['token'] );
            $rating               = intval( $_POST['rating'] );
            $rating_cleanliness   = intval( $_POST['rating_cleanliness'] ?? 5 );
            $rating_communication = intval( $_POST['rating_communication'] ?? 5 );
            $rating_location      = intval( $_POST['rating_location'] ?? 5 );
            $rating_value         = intval( $_POST['rating_value'] ?? 5 );
            $comment              = sanitize_textarea_field( $_POST['comment'] );

            // Bypass validation for test mode
            if ( $booking_id === 0 && $token === 'test_token' ) {
                $booking = (object)[
                    'id'             => 0,
                    'homestay_id'    => 0,
                    'customer_name'  => 'Test Guest',
                    'customer_email' => 'test@example.com'
                ];
                // Do not actually insert the test review into the database, just show success!
                wp_redirect( add_query_arg( [ 'hhb_review' => '1', 'success' => '1' ], home_url( '/' ) ) );
                exit;
            } else {
                if ( ! self::validate_token( $booking_id, $token ) ) {
                    wp_die( __( 'Invalid or expired review link.', 'himalayan-homestay-bookings' ) );
                }

                $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_bookings} WHERE id = %d", $booking_id ) );
                if ( ! $booking ) { wp_die( 'Booking not found.' ); }

                // Check if already reviewed
                $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_reviews} WHERE booking_id = %d", $booking_id ) );
                if ( $exists ) {
                    wp_die( __( 'You have already submitted a review for this booking. Thank you!', 'himalayan-homestay-bookings' ) );
                }
            }

            if ( $rating < 1 || $rating > 5 || empty( $comment ) ) {
                wp_die( __( 'Please provide a valid rating (1-5) and a comment.', 'himalayan-homestay-bookings' ) );
            }

            $wpdb->insert( $table_reviews, [
                'booking_id'           => $booking->id,
                'homestay_id'          => $booking->homestay_id,
                'customer_name'        => $booking->customer_name,
                'customer_email'       => $booking->customer_email,
                'rating'               => $rating,
                'rating_cleanliness'   => $rating_cleanliness,
                'rating_communication' => $rating_communication,
                'rating_location'      => $rating_location,
                'rating_value'         => $rating_value,
                'comment'              => $comment,
                'status'               => 'approved', // Auto-approving for now
                'created_at'           => current_time( 'mysql' ),
            ] );

            wp_redirect( add_query_arg( [ 'hhb_review' => '1', 'success' => '1' ], home_url( '/' ) ) );
            exit;
        }
    }

    public static function intercept_review_template( $template ) {
        if ( isset( $_GET['hhb_review'] ) ) {
            if ( isset( $_GET['success'] ) ) {
                self::render_success_page();
                exit;
            }

            $booking_id = isset( $_GET['booking_id'] ) ? intval( $_GET['booking_id'] ) : 0;
            $token      = isset( $_GET['token'] ) ? sanitize_text_field( $_GET['token'] ) : '';

            // Temporary test bypass so the user can see the form UI!
            if ( isset( $_GET['test_review'] ) ) {
                self::render_standalone_page( 0, 'test_token' );
                exit;
            }

            if ( $booking_id > 0 && self::validate_token( $booking_id, $token ) ) {
                self::render_standalone_page( $booking_id, $token );
                exit;
            } else {
                wp_die( __( 'Invalid or expired review link.', 'himalayan-homestay-bookings' ) );
            }
        }
        return $template;
    }

    private static function validate_token( $booking_id, $token ): bool {
        global $wpdb;
        $table   = $wpdb->prefix . 'himalayan_bookings';
        $booking = $wpdb->get_row( $wpdb->prepare( "SELECT id, customer_email FROM {$table} WHERE id = %d", $booking_id ) );

        if ( ! $booking ) return false;

        $expected_token = md5( $booking->id . $booking->customer_email . wp_salt() );
        return hash_equals( $expected_token, $token );
    }

    private static function render_standalone_page( int $booking_id, string $token ): void {
        global $wpdb;
        $table   = $wpdb->prefix . 'himalayan_bookings';
        $table_reviews  = $wpdb->prefix . 'hhb_reviews';
        
        // Handle test bypass
        if ( $booking_id === 0 && $token === 'test_token' ) {
            $booking = (object)[
                'customer_name'  => 'Test Guest',
                'customer_email' => 'test@example.com',
                'homestay_id'    => 0,
            ];
        } else {
            $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $booking_id ) );
            
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_reviews} WHERE booking_id = %d", $booking_id ) );
            if ( $exists ) {
                wp_die( __( 'You have already submitted a review for this booking. Thank you!', 'himalayan-homestay-bookings' ) );
            }
        }

        $homestay = ($booking->homestay_id > 0) ? get_post( $booking->homestay_id ) : null;
        $title    = $homestay ? $homestay->post_title : 'Homestay';
        $home_url = home_url();

        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php esc_html_e( 'Review Your Stay', 'himalayan-homestay-bookings' ); ?> - <?php bloginfo( 'name' ); ?></title>
            <style>
                body {
                    margin: 0; padding: 0;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    background: #f7f9fb; color: #333;
                    display: flex; justify-content: center; align-items: center;
                    min-height: 100vh;
                }
                .hhb-review-container {
                    background: #fff; width: 100%; max-width: 500px;
                    border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.08);
                    overflow: hidden; text-align: center; margin: 20px;
                }
                .hhb-review-header {
                    background: linear-gradient(135deg, #f45c25, #e04010);
                    color: #fff; padding: 40px 20px 20px;
                }
                .hhb-review-header h1 { margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px; }
                .hhb-review-header p { margin: 10px 0 0; font-size: 15px; opacity: 0.9; }
                .hhb-review-body { padding: 30px; text-align: left; }
                
                .hhb-field { margin-bottom: 20px; }
                .hhb-field label { display: block; font-weight: 600; margin-bottom: 8px; color: #444; }
                .hhb-field textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; font-family: inherit; resize: vertical; min-height: 100px; box-sizing: border-box; }
                .hhb-field textarea:focus { border-color: #f45c25; outline: none; box-shadow: 0 0 0 3px rgba(244,92,37,0.1); }
                
                .star-rating { display: inline-flex; flex-direction: row-reverse; justify-content: flex-end; }
                .star-rating input { display: none; }
                .star-rating label { font-size: 32px; color: #ccc; cursor: pointer; transition: color 0.2s; padding-right: 4px; }
                .star-rating label:before { content: '★'; }
                .star-rating input:checked ~ label,
                .star-rating label:hover,
                .star-rating label:hover ~ label { color: #f5b301; }
                .sub-rating label { font-size: 24px; }
                
                .hhb-btn-submit {
                    display: block; width: 100%; background: #111; color: #fff;
                    text-decoration: none; padding: 14px; border: none; border-radius: 8px;
                    font-weight: 600; font-size: 16px; cursor: pointer; transition: all 0.3s;
                }
                .hhb-btn-submit:hover { background: #333; transform: translateY(-1px); }
            </style>
        </head>
        <body>
            <div class="hhb-review-container">
                <div class="hhb-review-header">
                    <h1><?php esc_html_e( 'How was your stay?', 'himalayan-homestay-bookings' ); ?></h1>
                    <p><?php echo esc_html( $title ); ?></p>
                </div>
                <div class="hhb-review-body">
                    <p style="margin-top:0; margin-bottom:24px; color:#666; font-size: 15px;">
                        <?php printf( __( 'Hi %s, your feedback helps other travelers make great decisions. Please leave a rating and short review below.', 'himalayan-homestay-bookings' ), esc_html( $booking->customer_name ) ); ?>
                    </p>
                    <form method="POST" action="">
                        <?php wp_nonce_field( 'hhb_submit_review_action', 'hhb_review_nonce' ); ?>
                        <input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking_id ); ?>">
                        <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
                        <input type="hidden" name="hhb_submit_review" value="1">
                        
                        <div class="hhb-field" style="text-align:center;">
                            <label style="font-size:18px; text-transform:uppercase; letter-spacing:1px; color:#111;"><?php esc_html_e( 'Overall Experience', 'himalayan-homestay-bookings' ); ?></label>
                            <div class="star-rating" style="justify-content:center; width:100%; margin-top:5px;">
                                <input type="radio" id="star5" name="rating" value="5" required /><label for="star5" title="5 stars"></label>
                                <input type="radio" id="star4" name="rating" value="4" /><label for="star4" title="4 stars"></label>
                                <input type="radio" id="star3" name="rating" value="3" /><label for="star3" title="3 stars"></label>
                                <input type="radio" id="star2" name="rating" value="2" /><label for="star2" title="2 stars"></label>
                                <input type="radio" id="star1" name="rating" value="1" /><label for="star1" title="1 star"></label>
                            </div>
                        </div>

                        <div style="display:flex; flex-wrap:wrap; gap:15px; margin-bottom:20px; background:#f9f9f9; padding:15px; border-radius:8px;">
                            <div class="hhb-field" style="flex:1 1 45%; margin-bottom:10px;">
                                <label style="font-size:13px; margin-bottom:2px;"><?php esc_html_e( 'Cleanliness', 'himalayan-homestay-bookings' ); ?></label>
                                <div class="star-rating sub-rating">
                                    <input type="radio" id="cl5" name="rating_cleanliness" value="5" checked/><label for="cl5"></label>
                                    <input type="radio" id="cl4" name="rating_cleanliness" value="4"/><label for="cl4"></label>
                                    <input type="radio" id="cl3" name="rating_cleanliness" value="3"/><label for="cl3"></label>
                                    <input type="radio" id="cl2" name="rating_cleanliness" value="2"/><label for="cl2"></label>
                                    <input type="radio" id="cl1" name="rating_cleanliness" value="1"/><label for="cl1"></label>
                                </div>
                            </div>
                            <div class="hhb-field" style="flex:1 1 45%; margin-bottom:10px;">
                                <label style="font-size:13px; margin-bottom:2px;"><?php esc_html_e( 'Communication', 'himalayan-homestay-bookings' ); ?></label>
                                <div class="star-rating sub-rating">
                                    <input type="radio" id="cm5" name="rating_communication" value="5" checked/><label for="cm5"></label>
                                    <input type="radio" id="cm4" name="rating_communication" value="4"/><label for="cm4"></label>
                                    <input type="radio" id="cm3" name="rating_communication" value="3"/><label for="cm3"></label>
                                    <input type="radio" id="cm2" name="rating_communication" value="2"/><label for="cm2"></label>
                                    <input type="radio" id="cm1" name="rating_communication" value="1"/><label for="cm1"></label>
                                </div>
                            </div>
                            <div class="hhb-field" style="flex:1 1 45%; margin-bottom:0px;">
                                <label style="font-size:13px; margin-bottom:2px;"><?php esc_html_e( 'Location', 'himalayan-homestay-bookings' ); ?></label>
                                <div class="star-rating sub-rating">
                                    <input type="radio" id="lc5" name="rating_location" value="5" checked/><label for="lc5"></label>
                                    <input type="radio" id="lc4" name="rating_location" value="4"/><label for="lc4"></label>
                                    <input type="radio" id="lc3" name="rating_location" value="3"/><label for="lc3"></label>
                                    <input type="radio" id="lc2" name="rating_location" value="2"/><label for="lc2"></label>
                                    <input type="radio" id="lc1" name="rating_location" value="1"/><label for="lc1"></label>
                                </div>
                            </div>
                            <div class="hhb-field" style="flex:1 1 45%; margin-bottom:0px;">
                                <label style="font-size:13px; margin-bottom:2px;"><?php esc_html_e( 'Value', 'himalayan-homestay-bookings' ); ?></label>
                                <div class="star-rating sub-rating">
                                    <input type="radio" id="vl5" name="rating_value" value="5" checked/><label for="vl5"></label>
                                    <input type="radio" id="vl4" name="rating_value" value="4"/><label for="vl4"></label>
                                    <input type="radio" id="vl3" name="rating_value" value="3"/><label for="vl3"></label>
                                    <input type="radio" id="vl2" name="rating_value" value="2"/><label for="vl2"></label>
                                    <input type="radio" id="vl1" name="rating_value" value="1"/><label for="vl1"></label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="hhb-field">
                            <label><?php esc_html_e( 'Your Review', 'himalayan-homestay-bookings' ); ?></label>
                            <textarea name="comment" placeholder="<?php esc_html_e( 'Tell us about your experience...', 'himalayan-homestay-bookings' ); ?>" required></textarea>
                        </div>
                        
                        <button type="submit" class="hhb-btn-submit"><?php esc_html_e( 'Submit Review', 'himalayan-homestay-bookings' ); ?></button>
                    </form>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    private static function render_success_page(): void {
        $home_url = home_url();
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php esc_html_e( 'Thank You', 'himalayan-homestay-bookings' ); ?></title>
            <style>
                body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f7f9fb; color: #333; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
                .hhb-review-container { background: #fff; width: 100%; max-width: 500px; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); overflow: hidden; text-align: center; margin: 20px; padding: 50px 30px; }
                .hhb-review-container svg { width: 80px; height: 80px; fill: #2e7d32; margin-bottom: 20px; }
                h1 { margin: 0 0 10px; font-size: 28px; font-weight: 700; }
                p { color: #666; font-size: 16px; margin: 0 0 30px; }
                .hhb-btn-home { display: inline-block; background: #111; color: #fff; text-decoration: none; padding: 14px 32px; border-radius: 30px; font-weight: 600; font-size: 15px; }
            </style>
        </head>
        <body>
            <div class="hhb-review-container">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                <h1><?php esc_html_e( 'Thank You!', 'himalayan-homestay-bookings' ); ?></h1>
                <p><?php esc_html_e( 'Your review has been successfully submitted and will help other travelers!', 'himalayan-homestay-bookings' ); ?></p>
                <a href="<?php echo esc_url( $home_url ); ?>" class="hhb-btn-home">&larr; <?php esc_html_e( 'Return to Homepage', 'himalayan-homestay-bookings' ); ?></a>
            </div>
        </body>
        </html>
        <?php
    }
}
