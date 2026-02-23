<?php
/**
 * Availability Engine
 *
 * Checks date overlaps against existing bookings and active holds,
 * supports buffer/turnaround days between bookings.
 *
 * @package Himalayan\Homestay\Domain\Availability
 */

namespace Himalayan\Homestay\Domain\Availability;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AvailabilityEngine {

    /**
     * Check if requested dates overlap with existing bookings, holds, or buffer zones.
     */
    public function check_date_overlap( $homestay_id, $check_in, $check_out ): bool {
        global $wpdb;

        $bookings_table = $wpdb->prefix . 'himalayan_bookings';
        $holds_table    = $wpdb->prefix . 'himalayan_booking_hold';

        // Get buffer days for this property.
        $buffer_days = (int) get_post_meta( $homestay_id, 'hhb_buffer_days', true );

        // Expand the check window by buffer days to account for turnaround time.
        $effective_check_in  = $check_in;
        if ( $buffer_days > 0 ) {
            $dt = new \DateTime( $check_in );
            $dt->modify( "-{$buffer_days} days" );
            $effective_check_in = $dt->format( 'Y-m-d' );
        }

        $effective_check_out = $check_out;
        if ( $buffer_days > 0 ) {
            $dt = new \DateTime( $check_out );
            $dt->modify( "+{$buffer_days} days" );
            $effective_check_out = $dt->format( 'Y-m-d' );
        }

        // Check confirmed/pending bookings.
        $booking_query = $wpdb->prepare( "
            SELECT id FROM {$bookings_table}
            WHERE homestay_id = %d
            AND status IN ('pending_inquiry', 'approved', 'payment_pending', 'confirmed')
            AND (
                (check_in <= %s AND check_out > %s) OR
                (check_in < %s AND check_out >= %s) OR
                (check_in >= %s AND check_out <= %s)
            )
            LIMIT 1
        ", $homestay_id,
            $effective_check_in, $effective_check_in,
            $effective_check_out, $effective_check_out,
            $effective_check_in, $effective_check_out
        );

        if ( $wpdb->get_var( $booking_query ) ) {
            return true;
        }

        // Check active temporary holds.
        $hold_query = $wpdb->prepare( "
            SELECT id FROM {$holds_table}
            WHERE homestay_id = %d
            AND expires_at > %s
            AND (
                (check_in <= %s AND check_out > %s) OR
                (check_in < %s AND check_out >= %s) OR
                (check_in >= %s AND check_out <= %s)
            )
            LIMIT 1
        ", $homestay_id, current_time( 'mysql', 1 ),
            $check_in, $check_in,
            $check_out, $check_out,
            $check_in, $check_out
        );

        if ( $wpdb->get_var( $hold_query ) ) {
            return true;
        }

        return false; // Available!
    }

    /**
     * Create a temporary hold for 15 minutes.
     */
    public function create_temp_hold( $homestay_id, $check_in, $check_out, $session_id ) {
        if ( $this->check_date_overlap( $homestay_id, $check_in, $check_out ) ) {
            throw new \Exception( 'Dates are no longer available.' );
        }

        global $wpdb;
        $holds_table = $wpdb->prefix . 'himalayan_booking_hold';
        $expires_at  = date( 'Y-m-d H:i:s', current_time( 'timestamp', 1 ) + ( 15 * 60 ) );

        $inserted = $wpdb->insert( $holds_table, [
            'homestay_id' => $homestay_id,
            'session_id'  => $session_id,
            'check_in'    => $check_in,
            'check_out'   => $check_out,
            'expires_at'  => $expires_at,
        ], [ '%d', '%s', '%s', '%s', '%s' ] );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Release expired holds (called by cron).
     */
    public function release_expired_holds(): void {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}himalayan_booking_hold WHERE expires_at < %s",
            current_time( 'mysql', 1 )
        ) );
    }
}
