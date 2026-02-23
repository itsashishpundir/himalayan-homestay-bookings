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

        // Check Availability.
        register_rest_route( $ns, '/check-availability', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'check_availability' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'homestay_id' => [ 'required' => true, 'validate_callback' => 'is_numeric' ],
                'check_in'    => [ 'required' => true, 'validate_callback' => [ __CLASS__, 'validate_date' ] ],
                'check_out'   => [ 'required' => true, 'validate_callback' => [ __CLASS__, 'validate_date' ] ],
            ],
        ] );

        // Calculate Price (detailed, with services).
        register_rest_route( $ns, '/calculate-price', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'calculate_price' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'homestay_id' => [ 'required' => true, 'validate_callback' => 'is_numeric' ],
                'check_in'    => [ 'required' => true, 'validate_callback' => [ __CLASS__, 'validate_date' ] ],
                'check_out'   => [ 'required' => true, 'validate_callback' => [ __CLASS__, 'validate_date' ] ],
                'guests'      => [ 'required' => false, 'default' => 1, 'validate_callback' => 'is_numeric' ],
                'services'    => [ 'required' => false, 'default' => [] ],
            ],
        ] );

        // Create Booking.
        register_rest_route( $ns, '/create-booking', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'create_booking' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'homestay_id'    => [ 'required' => true, 'validate_callback' => 'is_numeric' ],
                'check_in'       => [ 'required' => true, 'validate_callback' => [ __CLASS__, 'validate_date' ] ],
                'check_out'      => [ 'required' => true, 'validate_callback' => [ __CLASS__, 'validate_date' ] ],
                'customer_name'  => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'customer_email' => [ 'required' => true, 'sanitize_callback' => 'sanitize_email' ],
                'customer_phone' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
                'guests'         => [ 'required' => false, 'default' => 1, 'validate_callback' => 'is_numeric' ],
                'notes'          => [ 'required' => false, 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ],
                'services'       => [ 'required' => false, 'default' => [] ],
            ],
        ] );
    }

    public static function validate_date( $date, $request, $param ): bool {
        $d = \DateTime::createFromFormat( 'Y-m-d', $date );
        return $d && $d->format( 'Y-m-d' ) === $date;
    }

    // =========================================================================
    // Endpoints
    // =========================================================================

    public static function check_availability( \WP_REST_Request $request ) {
        $homestay_id = (int) $request->get_param( 'homestay_id' );
        $check_in    = $request->get_param( 'check_in' );
        $check_out   = $request->get_param( 'check_out' );

        // Validate min/max nights.
        $min_nights = (int) get_post_meta( $homestay_id, 'hhb_min_nights', true ) ?: 1;
        $max_nights = (int) get_post_meta( $homestay_id, 'hhb_max_nights', true ) ?: 365;
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
        $is_overlap   = $availability->check_date_overlap( $homestay_id, $check_in, $check_out );

        return rest_ensure_response( [
            'available' => ! $is_overlap,
            'nights'    => $nights,
            'message'   => $is_overlap ? 'Selected dates are not available.' : 'Dates are available!',
        ] );
    }

    public static function calculate_price( \WP_REST_Request $request ) {
        $homestay_id = (int) $request->get_param( 'homestay_id' );
        $check_in    = $request->get_param( 'check_in' );
        $check_out   = $request->get_param( 'check_out' );
        $guests      = (int) $request->get_param( 'guests' );
        $service_ids = array_map( 'intval', (array) $request->get_param( 'services' ) );

        $pricing = new PricingEngine();
        $result  = $pricing->calculate_detailed_price( $homestay_id, $check_in, $check_out, $guests, $service_ids );

        if ( ( $result['grand_total'] ?? 0 ) <= 0 ) {
            return new \WP_Error( 'pricing_error', $result['error'] ?? 'Unable to calculate price.', [ 'status' => 400 ] );
        }

        return rest_ensure_response( $result );
    }

    public static function create_booking( \WP_REST_Request $request ) {
        $homestay_id = (int) $request->get_param( 'homestay_id' );
        $check_in    = $request->get_param( 'check_in' );
        $check_out   = $request->get_param( 'check_out' );
        $guests      = (int) $request->get_param( 'guests' );
        $service_ids = array_map( 'intval', (array) $request->get_param( 'services' ) );

        // 1. Re-verify availability.
        $availability = new AvailabilityEngine();
        if ( $availability->check_date_overlap( $homestay_id, $check_in, $check_out ) ) {
            return new \WP_Error( 'unavailable', 'Dates are no longer available.', [ 'status' => 409 ] );
        }

        // 2. Calculate final price.
        $pricing    = new PricingEngine();
        $price_data = $pricing->calculate_detailed_price( $homestay_id, $check_in, $check_out, $guests, $service_ids );

        if ( ( $price_data['grand_total'] ?? 0 ) <= 0 ) {
            return new \WP_Error( 'pricing_error', 'Invalid price calculation.', [ 'status' => 400 ] );
        }

        // 3. Create temporary hold.
        $session_id = wp_generate_password( 24, false );
        try {
            $availability->create_temp_hold( $homestay_id, $check_in, $check_out, $session_id );
        } catch ( \Exception $e ) {
            return new \WP_Error( 'hold_failed', $e->getMessage(), [ 'status' => 409 ] );
        }

        // 4. Create booking.
        $booking_manager = new BookingManager();
        $booking_id      = $booking_manager->create_booking( [
            'homestay_id'    => $homestay_id,
            'customer_name'  => $request->get_param( 'customer_name' ),
            'customer_email' => $request->get_param( 'customer_email' ),
            'customer_phone' => $request->get_param( 'customer_phone' ) ?? '',
            'check_in'       => $check_in,
            'check_out'      => $check_out,
            'guests'         => $guests,
            'total_price'    => $price_data['grand_total'],
            'deposit_amount' => $price_data['deposit_amount'],
            'balance_due'    => $price_data['balance_due'],
            'notes'          => $request->get_param( 'notes' ),
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

        return rest_ensure_response( [
            'success'    => true,
            'message'    => 'Booking request received successfully.',
            'booking_id' => $booking_id,
            'status'     => 'pending_inquiry',
            'total'      => $price_data['grand_total'],
        ] );
    }
}
