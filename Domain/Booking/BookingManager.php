<?php
/**
 * Booking Manager
 *
 * Handles the creation and lifecycle management of booking records,
 * including deposit tracking and status transitions.
 *
 * @package Himalayan\Homestay\Domain\Booking
 */

namespace Himalayan\Homestay\Domain\Booking;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class BookingManager {

    /**
     * Create a new booking record.
     *
     * @param array $data Booking data.
     * @return int|false Booking ID on success, false on failure.
     */
    public function create_booking( array $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_bookings';

        $inserted = $wpdb->insert( $table, [
            'homestay_id'    => $data['homestay_id'],
            'customer_name'  => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'customer_phone' => $data['customer_phone'] ?? '',
            'check_in'       => $data['check_in'],
            'check_out'      => $data['check_out'],
            'guests'         => $data['guests'] ?? 1,
            'adults'         => $data['adults'] ?? $data['guests'] ?? 1,
            'children'       => $data['children'] ?? 0,
            'total_price'    => $data['total_price'],
            'deposit_amount' => $data['deposit_amount'] ?? $data['total_price'],
            'balance_due'    => $data['balance_due'] ?? 0,
            'status'         => 'pending_inquiry',
            'payment_token'  => wp_generate_password( 32, false ),
            'notes'          => $data['notes'] ?? '',
        ] );

        if ( $inserted ) {
            $booking_id = $wpdb->insert_id;
            /**
             * Fires when a new booking is created.
             *
             * @param int $booking_id The new booking ID.
             */
            do_action( 'himalayan_booking_created', $booking_id );
            return $booking_id;
        }

        return false;
    }

    /**
     * Approve a pending booking.
     */
    public function approve_booking( int $booking_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_bookings';

        $updated = $wpdb->update(
            $table,
            [ 'status' => 'approved' ],
            [ 'id' => $booking_id ],
            [ '%s' ],
            [ '%d' ]
        );

        if ( $updated ) {
            do_action( 'himalayan_booking_approved', $booking_id );
            return true;
        }
        return false;
    }

    /**
     * Confirm payment for a booking.
     */
    public function confirm_payment( int $booking_id, string $transaction_id = '', string $gateway = '' ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_bookings';

        $updated = $wpdb->update(
            $table,
            [
                'status'         => 'confirmed',
                'transaction_id' => $transaction_id,
                'gateway'        => $gateway,
            ],
            [ 'id' => $booking_id ]
        );

        if ( $updated ) {
            do_action( 'himalayan_payment_confirmed', $booking_id );
            return true;
        }
        return false;
    }

    /**
     * Cancel a booking.
     */
    public function cancel_booking( int $booking_id ): bool {
        global $wpdb;
        $updated = $wpdb->update(
            $wpdb->prefix . 'himalayan_bookings',
            [ 'status' => 'cancelled' ],
            [ 'id' => $booking_id ],
            [ '%s' ],
            [ '%d' ]
        );

        if ( $updated ) {
            do_action( 'himalayan_booking_cancelled', $booking_id );
            return true;
        }
        return false;
    }

    /**
     * Get a single booking by ID.
     */
    public function get_booking( int $booking_id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}himalayan_bookings WHERE id = %d",
            $booking_id
        ) );
    }
}
