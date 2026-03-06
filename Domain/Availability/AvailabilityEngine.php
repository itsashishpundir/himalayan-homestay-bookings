<?php
/**
 * Availability Engine
 *
 * Checks date overlaps against existing bookings and active holds for Rooms,
 * supports buffer/turnaround days between bookings.
 *
 * @package Himalayan\Homestay\Domain\Availability
 */

namespace Himalayan\Homestay\Domain\Availability;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AvailabilityEngine {

    /**
     * Check if requested dates overlap with existing bookings, holds, or buffer zones for a specific room.
     */
    public function check_date_overlap( $room_id, $check_in, $check_out, $requested_quantity = 1 ): bool {
        global $wpdb;

        // ── Proactively purge expired holds so stale data never blocks bookings.
        $this->release_expired_holds();

        $homestay_id = wp_get_post_parent_id( $room_id );
        if ( ! $homestay_id ) {
            $homestay_id = get_post_meta( $room_id, '_hhb_homestay_id', true ); 
        }

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

        $base_qty = (int) get_post_meta( $room_id, 'room_quantity', true ) ?: 1;
        if ( $base_qty < $requested_quantity ) {
            return true; // Request exceeds physical layout limits natively
        }

        // ── 1. Check Room Availability Ledger (O(N dates) Indexed Lookup) ───────────
        $ledger_table = $wpdb->prefix . 'himalayan_room_availability';
        $placeholders = implode( ',', array_fill( 0, count( $dates_to_check ), '%s' ) );
        $query_args   = array_merge( [ $room_id ], $dates_to_check );

        $ledger_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT date, status, quantity_available FROM {$ledger_table} WHERE room_id = %d AND date IN ({$placeholders})",
            ...$query_args
        ) );

        foreach ( $ledger_rows as $row ) {
            if ( $row->status === 'blocked' ) {
                return true; // Overlaps
            }
            if ( (int) $row->quantity_available < $requested_quantity ) {
                return true; // Not enough quantity remaining on this day
            }
        }

        // ── 2. Check Temporary Holds ─────────────────────────────────────
        $holds_table = $wpdb->prefix . 'himalayan_booking_hold';
        $hold_query  = $wpdb->prepare( "
            SELECT COUNT(id) FROM {$holds_table}
            WHERE room_id = %d
            AND expires_at > %s
            AND (
                (check_in < %s AND check_out > %s) OR
                (check_in < %s AND check_out > %s) OR
                (check_in >= %s AND check_out <= %s)
            )
        ", $room_id, current_time( 'mysql', 1 ),
            $end_dt->format( 'Y-m-d' ), $start_dt->format( 'Y-m-d' ),
            $end_dt->format( 'Y-m-d' ), $start_dt->format( 'Y-m-d' ),
            $start_dt->format( 'Y-m-d' ), $end_dt->format( 'Y-m-d' )
        );

        $active_holds_count = (int) $wpdb->get_var( $hold_query );

        if ( $active_holds_count > 0 ) {
            $all_holds = $wpdb->get_results( $wpdb->prepare( "
                SELECT check_in, check_out, quantity FROM {$holds_table}
                WHERE room_id = %d AND expires_at > %s
            ", $room_id, current_time( 'mysql', 1 ) ) );
            
            // Re-check each date exactly against holds and ledger limits
            foreach ( $dates_to_check as $date ) {
                $available_for_date = $base_qty;
                
                // Subtract ledger limits
                foreach ( $ledger_rows as $row ) {
                    if ( $row->date === $date ) {
                        $available_for_date = (int) $row->quantity_available;
                        break;
                    }
                }
                
                // Subtract active holds overlapping this day
                $date_obj = new \DateTime($date);
                foreach ( $all_holds as $hold ) {
                    $hc_in = new \DateTime($hold->check_in);
                    $hc_out = new \DateTime($hold->check_out);
                    
                    if ( $date_obj >= $hc_in && $date_obj < $hc_out ) {
                        $available_for_date -= (int) $hold->quantity;
                    }
                }
                
                if ( $available_for_date < $requested_quantity ) {
                    return true;
                }
            }
        }

        return false; // Available!
    }

    /**
     * Create a temporary hold for 15 minutes.
     */
    public function create_temp_hold( $homestay_id, $room_id, $check_in, $check_out, $session_id, $quantity = 1 ) {
        if ( $this->check_date_overlap( $room_id, $check_in, $check_out, $quantity ) ) {
            throw new \Exception( 'Dates are no longer available for this room.' );
        }

        global $wpdb;
        $holds_table = $wpdb->prefix . 'himalayan_booking_hold';
        $expires_at  = date( 'Y-m-d H:i:s', current_time( 'timestamp', 1 ) + ( 15 * 60 ) );

        $inserted = $wpdb->insert( $holds_table, [
            'homestay_id' => $homestay_id,
            'room_id'     => $room_id,
            'session_id'  => $session_id,
            'check_in'    => $check_in,
            'check_out'   => $check_out,
            'quantity'    => $quantity,
            'expires_at'  => $expires_at,
        ], [ '%d', '%d', '%s', '%s', '%s', '%d', '%s' ] );

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
