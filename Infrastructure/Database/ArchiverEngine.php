<?php
/**
 * Archiver Engine
 *
 * Handles offloading completed, 2-year-old bookings from the hot
 * himalayan_bookings table into the himalayan_bookings_archive table
 * to maintain read/write performance at massive scale.
 *
 * @package Himalayan\Homestay\Infrastructure\Database
 */

namespace Himalayan\Homestay\Infrastructure\Database;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ArchiverEngine {

    public static function init(): void {
        if ( ! wp_next_scheduled( 'hhb_daily_archival_sweep' ) ) {
            wp_schedule_event( time(), 'daily', 'hhb_daily_archival_sweep' );
        }

        add_action( 'hhb_daily_archival_sweep', [ __CLASS__, 'process_archives' ] );
    }

    /**
     * Finds terminal bookings older than 24 months and moves them to cold storage.
     */
    public static function process_archives(): void {
        global $wpdb;

        $source_table = $wpdb->prefix . 'himalayan_bookings';
        $target_table = $wpdb->prefix . 'himalayan_bookings_archive';

        $cutoff_date  = gmdate( 'Y-m-d', strtotime( '-24 months' ) );
        $terminal_states = implode( "','", [ 'confirmed', 'refunded', 'cancelled' ] );

        // Get IDs to archive first to keep transaction small
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$source_table}
             WHERE status IN ('{$terminal_states}')
               AND check_out < %s
             LIMIT 500", // Process chunk to prevent memory exhaustion
            $cutoff_date
        ) );

        if ( empty( $ids ) ) {
            update_option( 'hhb_cron_last_ran_archival_sweep', time() );
            return;
        }

        $id_list = implode( ',', array_map( 'intval', $ids ) );

        $wpdb->query( 'START TRANSACTION' );

        // 1. Copy to archive
        $columns = "id, homestay_id, customer_name, customer_email, customer_phone, check_in, check_out, guests, adults, children, total_price, admin_commission, host_payout, deposit_amount, balance_due, status, payment_token, payment_expires_at, gateway, transaction_id, refund_id, refund_amount, refund_status, refunded_at, invoice_number, notes, created_at, updated_at";
        
        $inserted = $wpdb->query( "
            INSERT IGNORE INTO {$target_table} ({$columns})
            SELECT {$columns} FROM {$source_table}
            WHERE id IN ({$id_list})
        " );

        if ( $inserted === false ) {
            error_log( 'HHB Archiver: Failed to INSERT INTO archive table. Aborting.' );
            $wpdb->query( 'ROLLBACK' );
            return;
        }

        // 2. Remove from active bookings
        $deleted = $wpdb->query( "DELETE FROM {$source_table} WHERE id IN ({$id_list})" );

        if ( $deleted === false ) {
            error_log( 'HHB Archiver: Failed to DELETE FROM bookings table. Aborting.' );
            $wpdb->query( 'ROLLBACK' );
            return;
        }

        // 3. Remove orphaned availability ledger entries (failsafe, they should already be gone for refunded/cancelled, and past dates are irrelevant, but good hygiene)
        $wpdb->query( "DELETE FROM {$wpdb->prefix}himalayan_availability_ledger WHERE booking_id IN ({$id_list})" );

        $wpdb->query( 'COMMIT' );

        error_log( sprintf( 'HHB Archiver: Successfully archived %d stale bookings.', count( $ids ) ) );
        
        update_option( 'hhb_cron_last_ran_archival_sweep', time() );
    }
}
