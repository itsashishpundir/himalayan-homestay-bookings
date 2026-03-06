<?php
/**
 * REST API Controller
 *
 * Provides public endpoints for checking availability, calculating
 * detailed prices (with extra services), and creating bookings.
 *
 * @package Himalayan\Homestay\Infrastructure\API
 */

namespace Himalayan\Homestay\Infrastructure\API;

use Himalayan\Homestay\Domain\Availability\AvailabilityEngine;
use Himalayan\Homestay\Domain\Booking\BookingManager;
use Himalayan\Homestay\Domain\Pricing\PricingEngine;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class RESTController {

    public static function init(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes(): void {
        $ns = 'himalayan/v1';

        // List Rooms for a Homestay
        register_rest_route( $ns, '/rooms', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_rooms' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'homestay_id' => [ 'required' => true, 'validate_callback' => function( $param, $request, $key ) { return is_numeric( $param ); } ],
            ],
        ] );

        // Check Availability.
        register_rest_route( $ns, '/check-availability', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'check_availability' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'room_id'   => [ 'required' => true, 'validate_callback' => function( $param, $request, $key ) { return is_numeric( $param ); } ],
                'check_in'  => [ 'required' => true, 'validate_callback' => [ __CLASS__, 'validate_date' ] ],
                'check_out' => [ 'required' => true, 'validate_callback' => [ __CLASS__, 'validate_date' ] ],
                'quantity'  => [ 'required' => false, 'default' => 1, 'validate_callback' => function( $param, $request, $key ) { return is_numeric( $param ); } ],
            ],
        ] );

        // Calculate Price (detailed, with services).
        register_rest_route( $ns, '/calculate-price', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'calculate_price' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'room_id'   => [ 'required' => true, 'validate_callback' => function( $param, $request, $key ) { return is_numeric( $param ); } ],
                'check_in'  => [ 'required' => true, 'validate_callback' => [ __CLASS__, 'validate_date' ] ],
                'check_out' => [ 'required' => true, 'validate_callback' => [ __CLASS__, 'validate_date' ] ],
                'guests'    => [ 'required' => false, 'default' => 1, 'validate_callback' => function( $param, $request, $key ) { return is_numeric( $param ); } ],
                'services'  => [ 'required' => false, 'default' => [] ],
            ],
        ] );

        // Create Booking.
        register_rest_route( $ns, '/create-booking', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'create_booking' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'room_id'        => [ 'required' => true, 'validate_callback' => function( $param, $request, $key ) { return is_numeric( $param ); } ],
                'check_in'       => [ 'required' => true, 'validate_callback' => [ __CLASS__, 'validate_date' ] ],
                'check_out'      => [ 'required' => true, 'validate_callback' => [ __CLASS__, 'validate_date' ] ],
                'customer_name'  => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'customer_email' => [ 'required' => true, 'sanitize_callback' => 'sanitize_email' ],
                'customer_phone' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
                'guests'         => [ 'required' => false, 'default' => 1, 'validate_callback' => function( $param, $request, $key ) { return is_numeric( $param ); } ],
                'quantity'       => [ 'required' => false, 'default' => 1, 'validate_callback' => function( $param, $request, $key ) { return is_numeric( $param ); } ],
                'notes'          => [ 'required' => false, 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ],
                'services'       => [ 'required' => false, 'default' => [] ],
            ],
        ] );

        // Razorpay Verify Endpoint
        register_rest_route( $ns, '/razorpay-verify', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'verify_razorpay_payment' ],
            'permission_callback' => '__return_true', // Public endpoint natively verifies hash signature
            'args'                => [
                'razorpay_payment_id' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'razorpay_order_id'   => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'razorpay_signature'  => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'booking_id'          => [ 'required' => true, 'validate_callback' => function( $param, $request, $key ) { return is_numeric( $param ); } ],
                'token'               => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        // PayPal Verify Endpoint
        register_rest_route( $ns, '/paypal-verify', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'verify_paypal_payment' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'paypal_order_id'     => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'booking_id'          => [ 'required' => true, 'validate_callback' => function( $param, $request, $key ) { return is_numeric( $param ); } ],
                'token'               => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        // Drop Booking (Cancelled Payment)
        register_rest_route( $ns, '/drop-booking', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'drop_booking' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'booking_id' => [ 'required' => true, 'validate_callback' => function( $param, $request, $key ) { return is_numeric( $param ); } ],
                'token'      => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        // Razorpay Server-to-Server Webhook (idempotent, transaction-safe)
        register_rest_route( $ns, '/razorpay-webhook', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'handle_razorpay_webhook' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public static function validate_date( $date, $request, $param ): bool {
        $d = \DateTime::createFromFormat( 'Y-m-d', $date );
        return $d && $d->format( 'Y-m-d' ) === $date;
    }

    // =========================================================================
    // Endpoints
    // =========================================================================

    public static function get_rooms( \WP_REST_Request $request ) {
        $homestay_id = (int) $request->get_param( 'homestay_id' );
        
        $rooms = get_children( [
            'post_parent' => $homestay_id,
            'post_type'   => 'hhb_room',
            'post_status' => 'publish',
            'numberposts' => -1,
        ] );

        $data = [];
        foreach ( $rooms as $room ) {
            $data[] = [
                'id'            => $room->ID,
                'name'          => $room->post_title,
                'base_price'    => (float) get_post_meta( $room->ID, 'room_base_price', true ),
                'max_guests'    => (int) get_post_meta( $room->ID, 'room_max_guests', true ),
                'room_quantity' => (int) get_post_meta( $room->ID, 'room_quantity', true ),
            ];
        }

        return rest_ensure_response( [ 'success' => true, 'rooms' => $data ] );
    }

    public static function check_availability( \WP_REST_Request $request ) {
        $room_id   = (int) $request->get_param( 'room_id' );
        $check_in  = $request->get_param( 'check_in' );
        $check_out = $request->get_param( 'check_out' );
        $qty       = (int) $request->get_param( 'quantity' ) ?: 1;

        $homestay_id = wp_get_post_parent_id( $room_id );
        if ( ! $homestay_id ) {
            $homestay_id = (int) get_post_meta( $room_id, '_hhb_homestay_id', true );
        }

        // Validate min/max nights.
        $min_nights = (int) get_post_meta( $room_id, 'room_min_nights', true ) ?: (int) get_post_meta( $homestay_id, 'hhb_min_nights', true ) ?: 1;
        $max_nights = (int) get_post_meta( $room_id, 'room_max_nights', true ) ?: (int) get_post_meta( $homestay_id, 'hhb_max_nights', true ) ?: 365;
        $nights     = (int) ( ( strtotime( $check_out ) - strtotime( $check_in ) ) / DAY_IN_SECONDS );

        if ( $nights < $min_nights ) {
            return rest_ensure_response( [
                'available' => false,
                'message'   => sprintf( 'Minimum stay is %d night(s).', $min_nights ),
            ] );
        }
        if ( $nights > $max_nights ) {
            return rest_ensure_response( [
                'available' => false,
                'message'   => sprintf( 'Maximum stay is %d night(s).', $max_nights ),
            ] );
        }

        $availability = new AvailabilityEngine();
        $is_overlap   = $availability->check_date_overlap( $room_id, $check_in, $check_out, $qty );

        return rest_ensure_response( [
            'available' => ! $is_overlap,
            'nights'    => $nights,
            'message'   => $is_overlap ? 'Selected dates are not available.' : 'Dates are available!',
        ] );
    }

    public static function calculate_price( \WP_REST_Request $request ) {
        $room_id     = (int) $request->get_param( 'room_id' );
        $check_in    = $request->get_param( 'check_in' );
        $check_out   = $request->get_param( 'check_out' );
        $guests      = (int) $request->get_param( 'guests' );
        $service_ids = array_map( 'intval', (array) $request->get_param( 'services' ) );
        $coupon_code = sanitize_text_field( $request->get_param( 'coupon_code' ) ?? '' );

        $pricing = new PricingEngine();
        $result  = $pricing->calculate_detailed_price( $room_id, $check_in, $check_out, $guests, $service_ids, $coupon_code );

        if ( ( $result['grand_total'] ?? 0 ) <= 0 ) {
            return new \WP_Error( 'pricing_error', $result['error'] ?? 'Unable to calculate price.', [ 'status' => 400 ] );
        }

        return rest_ensure_response( $result );
    }

    public static function create_booking( \WP_REST_Request $request ) {
        // Rate limiting: max 5 booking attempts per IP per 15 minutes.
        $ip          = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
        $rate_key    = 'hhb_rate_' . md5( $ip );
        $attempts    = (int) get_transient( $rate_key );
        if ( $attempts >= 5 ) {
            return new \WP_Error( 'rate_limit', 'Too many booking attempts. Please wait 15 minutes and try again.', [ 'status' => 429 ] );
        }
        set_transient( $rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS );

        $room_id     = (int) $request->get_param( 'room_id' );
        $check_in    = $request->get_param( 'check_in' );
        $check_out   = $request->get_param( 'check_out' );
        $guests      = (int) $request->get_param( 'guests' );
        $quantity    = (int) $request->get_param( 'quantity' ) ?: 1;
        $service_ids = array_map( 'intval', (array) $request->get_param( 'services' ) );
        $coupon_code = sanitize_text_field( $request->get_param( 'coupon_code' ) ?? '' );
        $payment_mode_input = sanitize_text_field( $request->get_param( 'payment_mode' ) );

        $homestay_id = wp_get_post_parent_id( $room_id );
        if ( ! $homestay_id ) {
            $homestay_id = (int) get_post_meta( $room_id, '_hhb_homestay_id', true );
        }

        if ( ! $homestay_id ) {
            return new \WP_Error( 'invalid_room', 'Room does not belong to a valid homestay.', [ 'status' => 400 ] );
        }

        $payment_mode = $payment_mode_input;
        if ( $payment_mode_input === 'gateway' ) {
            $opts = get_option( \Himalayan\Homestay\Interface\Admin\SettingsPage::OPTION_KEY, [] );
            $rzp_enabled = ( ! empty( $opts['razorpay_enabled'] ) && 'yes' === $opts['razorpay_enabled'] );
            $paypal_enabled = ( ! empty( $opts['paypal_enabled'] ) && 'yes' === $opts['paypal_enabled'] );
            
            if ( $rzp_enabled && ( new \Himalayan\Homestay\Infrastructure\Payments\RazorpayGateway() )->is_active() ) {
                $payment_mode = 'Razorpay';
            } elseif ( $paypal_enabled && ( new \Himalayan\Homestay\Infrastructure\Payments\PayPalGateway() )->is_active() ) {
                $payment_mode = 'PayPal';
            } else {
                $payment_mode = 'Cash'; 
            }
        } elseif ( $payment_mode_input === 'cash' ) {
            $payment_mode = 'Cash';
        }

        // 1. Re-verify availability.
        $availability = new AvailabilityEngine();
        if ( $availability->check_date_overlap( $room_id, $check_in, $check_out, $quantity ) ) {
            return new \WP_Error( 'unavailable', 'Dates are no longer available for this room.', [ 'status' => 409 ] );
        }

        // 2. Calculate final price.
        $pricing    = new PricingEngine();
        $price_data = $pricing->calculate_detailed_price( $room_id, $check_in, $check_out, $guests, $service_ids, $coupon_code );

        if ( ( $price_data['grand_total'] ?? 0 ) <= 0 ) {
            return new \WP_Error( 'pricing_error', 'Invalid price calculation.', [ 'status' => 400 ] );
        }

        // 3. Create temporary hold.
        $session_id = wp_generate_password( 24, false );
        try {
            $availability->create_temp_hold( $homestay_id, $room_id, $check_in, $check_out, $session_id, $quantity );
        } catch ( \Exception $e ) {
            return new \WP_Error( 'hold_failed', $e->getMessage(), [ 'status' => 409 ] );
        }

        // 4. Create booking.
        $total    = $price_data['grand_total'];
        $admin_c  = round( $total * 0.10, 2 ); // 10% commission
        $host_p   = $total - $admin_c;

        $booking_manager = new BookingManager();
        $booking_id      = $booking_manager->create_booking( [
            'homestay_id'    => $homestay_id,
            'room_id'        => $room_id,
            'session_id'     => $session_id, // Pass down to delete temp hold immediately
            'customer_name'  => $request->get_param( 'customer_name' ),
            'customer_email' => $request->get_param( 'customer_email' ),
            'customer_phone' => $request->get_param( 'customer_phone' ) ?? '',
            'check_in'       => $check_in,
            'check_out'      => $check_out,
            'guests'         => $guests,
            'quantity'       => $quantity,
            'total_price'    => $total,
            'price_snapshot' => wp_json_encode( $price_data['breakdown'] ),
            'admin_commission' => $admin_c,
            'host_payout'    => $host_p,
            'deposit_amount' => $price_data['deposit_amount'],
            'balance_due'    => $price_data['balance_due'],
            'notes'          => $request->get_param( 'notes' ),
            'payment_mode'   => $payment_mode,
        ] );

        if ( ! $booking_id ) {
            return new \WP_Error( 'booking_failed', 'Failed to create booking.', [ 'status' => 500 ] );
        }

        // 5. Save selected services to pivot table.
        if ( ! empty( $service_ids ) && ! empty( $price_data['services_detail'] ) ) {
            global $wpdb;
            foreach ( $price_data['services_detail'] as $svc ) {
                $wpdb->insert( $wpdb->prefix . 'himalayan_booking_services', [
                    'booking_id' => $booking_id,
                    'service_id' => $svc['id'],
                    'quantity'   => 1,
                    'unit_price' => $svc['cost'],
                    'subtotal'   => $svc['cost'],
                ] );
            }
        }

        // Fetch the booking to get the payment token for the frontend.
        $new_booking = $booking_manager->get_booking( $booking_id );

        return rest_ensure_response( [
            'success'       => true,
            'message'       => 'Booking created. Proceed to payment.',
            'booking_id'    => $booking_id,
            'status'        => 'pending',
            'total'         => $price_data['grand_total'],
            'payment_token' => $new_booking ? $new_booking->payment_token : '',
            'mode'          => strtolower( str_replace( ' ', '_', $payment_mode ) ),
        ] );
    }

    /**
     * Shared confirmation logic enforcing idempotency, amount matching, and safe transaction handling.
     * Both the browser redirect and the S2S webhook MUST flow through this.
     */
    private static function process_razorpay_confirmation( int $booking_id, string $payment_id, string $event_id, int $amount_paise, string $currency ): array {
        global $wpdb;

        $booking_manager = new BookingManager();
        $booking = $booking_manager->get_booking( $booking_id );

        if ( ! $booking ) {
            return [ 'success' => false, 'status' => 400, 'message' => 'Booking not found.' ];
        }

        // Validate amount & currency
        $expected_amount = (int) round( (float) $booking->total_price * 100 );
        if ( $amount_paise !== $expected_amount ) {
            error_log( sprintf( 'HHB AMOUNT MISMATCH: Booking #%d expected %d paise, got %d paise.', $booking_id, $expected_amount, $amount_paise ) );
            return [ 'success' => false, 'status' => 400, 'message' => 'Amount mismatch.' ];
        }
        if ( strtoupper( $currency ) !== 'INR' ) {
            error_log( sprintf( 'HHB CURRENCY MISMATCH: Booking #%d expected INR, got %s.', $booking_id, $currency ) );
            return [ 'success' => false, 'status' => 400, 'message' => 'Currency mismatch.' ];
        }

        // Already processed?
        if ( $booking->status === 'confirmed' || $booking->status === 'refunded' ) {
            return [ 'success' => true, 'status' => 200, 'message' => 'Already processed.' ];
        }

        // 3. START TRANSACTION
        $wpdb->query( 'START TRANSACTION' );

        // 4. Idempotency gate: insert webhook/verification event
        $inserted = $wpdb->insert( $wpdb->prefix . 'himalayan_webhook_events', [
            'event_id'         => $event_id,
            'booking_id'       => $booking_id,
            'event_type'       => 'payment.captured',
            'raw_payload_hash' => hash( 'sha256', "event_{$event_id}_booking_{$booking_id}" ),
        ] );

        if ( ! $inserted ) {
            // Duplicate event_id or already being processed by another thread
            $wpdb->query( 'ROLLBACK' );
            return [ 'success' => true, 'status' => 200, 'message' => 'Already processed (duplicate event).' ];
        }

        // 5. Transition status
        $confirmed = $booking_manager->confirm_payment( $booking_id, $payment_id, 'razorpay' );

        if ( ! $confirmed ) {
            $wpdb->query( 'ROLLBACK' );
            error_log( sprintf( 'HHB Confirmation: transition_status failed for Booking #%d.', $booking_id ) );
            return [ 'success' => false, 'status' => 500, 'message' => 'Status transition failed.' ];
        }

        // 6. COMMIT
        $wpdb->query( 'COMMIT' );

        return [ 'success' => true, 'status' => 200, 'message' => 'Payment verified and booking confirmed.' ];
    }

    /**
     * Verify Razorpay Payment from the frontend Checkout.
     * This is the client-side verification (order_id|payment_id).
     */
    public function verify_razorpay_payment( \WP_REST_Request $request ) {
        $payment_id = $request->get_param('razorpay_payment_id');
        $order_id   = $request->get_param('razorpay_order_id');
        $signature  = $request->get_param('razorpay_signature');
        $booking_id = intval( $request->get_param('booking_id') );
        $token      = $request->get_param('token');

        if ( ! $payment_id || ! $order_id || ! $signature || ! $booking_id ) {
            return new \WP_Error( 'missing_params', 'Required parameters missing.', [ 'status' => 400 ] );
        }

        $booking_manager = new BookingManager();
        $booking         = $booking_manager->get_booking( $booking_id );

        if ( ! $booking || $booking->payment_token !== $token ) {
            return new \WP_Error( 'invalid_booking', 'Invalid booking or token.', [ 'status' => 400 ] );
        }

        // Step 1: Verify Signature BEFORE touching state/idempotency
        $settings = get_option( 'hhb_payment_settings', [] );
        $secret = $settings['razorpay_key_secret'] ?? '';

        if ( empty($secret) ) {
            return new \WP_Error( 'config_error', 'Gateway misconfigured.', [ 'status' => 500 ] );
        }

        $payload = $order_id . '|' . $payment_id;
        $expected_signature = hash_hmac( 'sha256', $payload, $secret );

        if ( ! hash_equals( $expected_signature, $signature ) ) {
            return new \WP_Error( 'invalid_signature', 'Signature verification failed.', [ 'status' => 400 ] );
        }

        // Step 2 & 3: Signature valid -> Pass to unified idempotency processor
        // We use a prefixed event ID for frontend verification to avoid colliding with real webhooks
        $event_id        = 'fe_verify_' . $payment_id;
        $expected_amount = (int) round( (float) $booking->total_price * 100 ); // implicit from order
        
        $result = self::process_razorpay_confirmation( $booking_id, $payment_id, $event_id, $expected_amount, 'INR' );
        
        if ( ! $result['success'] ) {
            return new \WP_Error( 'confirmation_error', $result['message'], [ 'status' => $result['status'] ] );
        }

        return rest_ensure_response( [ 'success' => true, 'message' => $result['message'] ] );
    }

    /**
     * Verify PayPal Payment from the frontend Checkout.
     * Standalone PayPal path â€” does NOT use Razorpay's amount/currency guards.
     */
    public static function verify_paypal_payment( \WP_REST_Request $request ) {
        global $wpdb;

        $paypal_order_id = sanitize_text_field( $request->get_param('paypal_order_id') );
        $booking_id      = intval( $request->get_param('booking_id') );
        $token           = sanitize_text_field( $request->get_param('token') );

        if ( ! $paypal_order_id || ! $booking_id ) {
            return new \WP_Error( 'missing_params', 'Required parameters missing.', [ 'status' => 400 ] );
        }

        // 1. Load booking and verify token
        $booking_manager = new BookingManager();
        $booking         = $booking_manager->get_booking( $booking_id );

        if ( ! $booking || $booking->payment_token !== $token ) {
            return new \WP_Error( 'invalid_booking', 'Invalid booking or token.', [ 'status' => 400 ] );
        }

        // 2. Already confirmed?
        if ( in_array( $booking->status, [ 'confirmed', 'refunded' ], true ) ) {
            return rest_ensure_response( [ 'success' => true, 'message' => 'Payment already processed.' ] );
        }

        // 3. Server-side verify / capture the PayPal order
        $gateway = new \Himalayan\Homestay\Infrastructure\Payments\PayPalGateway();
        $verified = $gateway->verify_order( $paypal_order_id );
        if ( ! $verified ) {
            return new \WP_Error( 'paypal_verify_failed', 'PayPal order verification failed.', [ 'status' => 400 ] );
        }

        // 4. Idempotency gate
        $event_id = 'paypal_' . $paypal_order_id;
        $wpdb->query( 'START TRANSACTION' );
        $inserted = $wpdb->insert( $wpdb->prefix . 'himalayan_webhook_events', [
            'event_id'         => $event_id,
            'booking_id'       => $booking_id,
            'event_type'       => 'paypal.payment.captured',
            'raw_payload_hash' => hash( 'sha256', "{$event_id}_{$booking_id}" ),
        ] );

        if ( ! $inserted ) {
            $wpdb->query( 'ROLLBACK' );
            return rest_ensure_response( [ 'success' => true, 'message' => 'Already processed.' ] );
        }

        // 5. Confirm booking with 'paypal' gateway
        try {
            $confirmed = $booking_manager->confirm_payment( $booking_id, $paypal_order_id, 'paypal' );
        } catch ( \Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            error_log( 'HHB PayPal confirm_payment exception: ' . $e->getMessage() );
            return new \WP_Error( 'confirmation_failed', $e->getMessage(), [ 'status' => 500 ] );
        }

        if ( ! $confirmed ) {
            $wpdb->query( 'ROLLBACK' );
            return new \WP_Error( 'transition_failed', 'Status transition failed.', [ 'status' => 500 ] );
        }

        $wpdb->query( 'COMMIT' );

        return rest_ensure_response( [ 'success' => true, 'message' => 'Payment verified and booking confirmed.' ] );
    }


    /**
     * Handle Razorpay server-to-server webhook.
     */
    public static function handle_razorpay_webhook( \WP_REST_Request $request ) {
        $raw_body  = $request->get_body();
        $signature = $request->get_header('x-razorpay-signature') ?? '';

        if ( empty( $raw_body ) || empty( $signature ) ) {
            return new \WP_REST_Response( [ 'error' => 'Empty payload or missing signature.' ], 400 );
        }

        // 1. Verify webhook signature BEFORE processing anything.
        $gateway = new \Himalayan\Homestay\Infrastructure\Payments\RazorpayGateway();
        if ( ! $gateway->verify_signature( $raw_body, $signature ) ) {
            error_log( 'HHB Webhook: Signature verification FAILED.' );
            return new \WP_REST_Response( [ 'error' => 'Invalid signature.' ], 400 );
        }

        $payload = json_decode( $raw_body, true );
        if ( ! $payload ) {
            return new \WP_REST_Response( [ 'error' => 'Invalid JSON.' ], 400 );
        }

        $event_type = $payload['event'] ?? '';
        
        // Extract Razorpay's own event ID if available
        $event_id = $payload['event_id'] ?? ( $payload['account_id'] . '_' . ( $payload['payload']['payment']['entity']['id'] ?? uniqid() ) );

        try {
            // Handle payment.captured / order.paid
            if ( in_array( $event_type, [ 'payment.captured', 'order.paid' ], true ) ) {
                $entity     = $payload['payload']['payment']['entity'] ?? [];
                $payment_id = $entity['id'] ?? '';
                $amount     = (int) ( $entity['amount'] ?? 0 );
                $currency   = $entity['currency'] ?? '';
                $notes      = $entity['notes'] ?? [];
                $booking_id = (int) ( $notes['booking_id'] ?? 0 );

                if ( ! $booking_id || ! $payment_id ) {
                    error_log( 'HHB Webhook: Missing booking_id or payment_id in payload.' );
                    return new \WP_REST_Response( [ 'status' => 'ignored' ], 200 );
                }

                // Step 2 & 3: Pass to unified idempotent processor
                $result = self::process_razorpay_confirmation( $booking_id, $payment_id, $event_id, $amount, $currency );
                
                if ( ! $result['success'] ) {
                    return new \WP_REST_Response( [ 'error' => $result['message'] ], $result['status'] );
                }

                return new \WP_REST_Response( [ 'status' => 'confirmed' ], 200 );
            }

            // Handle refund.processed (self-healing)
            if ( $event_type === 'refund.processed' ) {
                $entity     = $payload['payload']['refund']['entity'] ?? [];
                $refund_id  = $entity['id'] ?? '';
                $payment_id = $entity['payment_id'] ?? '';
                $amount     = (int) ( $entity['amount'] ?? 0 );
                $notes      = $entity['notes'] ?? $payload['payload']['payment']['entity']['notes'] ?? [];
                $booking_id = (int) ( $notes['booking_id'] ?? 0 );

                if ( $booking_id && $refund_id ) {
                    $booking_manager = new BookingManager();
                    $booking = $booking_manager->get_booking( $booking_id );

                    // Self-heal: if DB still says confirmed but Razorpay says refunded
                    if ( $booking && $booking->status === 'confirmed' ) {
                        try {
                            $booking_manager->refund_booking( $booking_id, $refund_id, (float) $amount );
                            error_log( sprintf( 'HHB Webhook Self-Heal: Booking #%d auto-refunded via webhook.', $booking_id ) );
                        } catch ( \Exception $e ) {
                            error_log( 'HHB Webhook Refund Self-Heal Error: ' . $e->getMessage() );
                        }
                    }
                }
                return new \WP_REST_Response( [ 'status' => 'refund_processed' ], 200 );
            }

            // Unhandled event type â€” acknowledge to stop retries
            return new \WP_REST_Response( [ 'status' => 'event_ignored' ], 200 );

        } catch ( \Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            error_log( sprintf( 'HHB Webhook Fatal Exception: %s', $e->getMessage() ) );
            return new \WP_REST_Response( [ 'error' => 'Internal error.' ], 500 );
        }
    }

    /**
     * Drop a booking explicitly when a user cancels payment or leaves the page.
     */
    public static function drop_booking( \WP_REST_Request $request ) {
        $booking_id = intval( $request->get_param('booking_id') );
        $token      = $request->get_param('token');

        if ( ! $booking_id || ! $token ) {
            return new \WP_Error( 'missing_params', 'Required parameters missing.', [ 'status' => 400 ] );
        }

        $booking_manager = new BookingManager();
        $booking         = $booking_manager->get_booking( $booking_id );

        // Basic verification
        if ( ! $booking || $booking->payment_token !== $token ) {
            return new \WP_Error( 'invalid_booking', 'Invalid booking or token.', [ 'status' => 400 ] );
        }

        // Only pending bookings can be dropped this way
        if ( 'pending' !== $booking->status ) {
            return new \WP_Error( 'invalid_status', 'Booking cannot be dropped from its current status.', [ 'status' => 400 ] );
        }

        $dropped = $booking_manager->drop_booking( $booking_id );
        
        if ( ! $dropped ) {
            return new \WP_Error( 'db_error', 'Failed to drop booking.', [ 'status' => 500 ] );
        }

        return rest_ensure_response( [
            'success' => true,
            'message' => 'Booking dropped.',
        ] );
    }
}
