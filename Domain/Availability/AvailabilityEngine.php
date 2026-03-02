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

        // ── Proactively purge expired holds so stale data never blocks bookings.
        // (This is a safety net in case the cleanup cron hasn't run yet.)
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}himalayan_booking_hold WHERE expires_at < %s",
            current_time( 'mysql', 1 ) // UTC timestamp
        ) );

        $bookings_table = $wpdb->prefix . 'himalayan_bookings';
        $holds_table    = $wpdb->prefix . 'himalayan_booking_hold';

        // Get buffer days for this property.
        $buffer_days = (int) get_post_meta( $homestay_id, 'hhb_buffer_days', true );

        $start_dt = new \DateTime( $check_in );
        if ( $buffer_days > 0 ) {
            $start_dt->modify( "-{$buffer_days} days" );
        }

        $end_dt = new \DateTime( $check_out );
        if ( $buffer_days > 0 ) {
            $end_dt->modify( "+{$buffer_days} days" );
        }

        // Build array of exact dates to check (excluding the checkout date itself)
        $dates_to_check = [];
        $period = new \DatePeriod( $start_dt, new \DateInterval( 'P1D' ), $end_dt );
        foreach ( $period as $dt ) {
            $dates_to_check[] = $dt->format( 'Y-m-d' );
        }

        if ( empty( $dates_to_check ) ) {
            return false;
        }

        // ── 1. Check Availability Ledger (O(1) Indexed Lookup) ───────────
        $ledger_table = $wpdb->prefix . 'himalayan_availability_ledger';
        $placeholders = implode( ',', array_fill( 0, count( $dates_to_check ), '%s' ) );
        $query_args   = array_merge( [ $homestay_id ], $dates_to_check );

        $ledger_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(id) FROM {$ledger_table} WHERE homestay_id = %d AND date IN ({$placeholders})",
            ...$query_args
        ) );

        if ( $ledger_count > 0 ) {
            return true;
        }

        // ── 2. Check Temporary Holds ─────────────────────────────────────
        // Temporary holds are still range-queried because they are purged every 15 minutes 
        // and physically cannot exceed a few dozen rows at scale.
        $holds_table = $wpdb->prefix . 'himalayan_booking_hold';
        $hold_query  = $wpdb->prepare( "
            SELECT id FROM {$holds_table}
            WHERE homestay_id = %d
            AND expires_at > %s
            AND (
                (check_in < %s AND check_out > %s) OR
                (check_in < %s AND check_out > %s) OR
                (check_in >= %s AND check_out <= %s)
            )
            LIMIT 1
        ", $homestay_id, current_time( 'mysql', 1 ),
            $end_dt->format( 'Y-m-d' ), $start_dt->format( 'Y-m-d' ),
            $end_dt->format( 'Y-m-d' ), $start_dt->format( 'Y-m-d' ),
            $start_dt->format( 'Y-m-d' ), $end_dt->format( 'Y-m-d' )
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
