<?php
/**
 * Database Installer
 *
 * Creates and upgrades all custom database tables for the booking system.
 *
 * @package Himalayan\Homestay\Infrastructure\Database
 */

namespace Himalayan\Homestay\Infrastructure\Database;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Installer {

    /** @var string Current database schema version. */
    const DB_VERSION = '4.0.0';

    public static function install() {
        global $wpdb;

        $wpdb->hide_errors();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $collate = '';
        if ( $wpdb->has_cap( 'collation' ) ) {
            $collate = $wpdb->get_charset_collate();
        }

        // =====================================================================
        // 1. Bookings Table (existing — enhanced with guests, notes, deposit)
        // =====================================================================
        $table_bookings = $wpdb->prefix . 'himalayan_bookings';
        $sql1 = "CREATE TABLE $table_bookings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            homestay_id bigint(20) unsigned NOT NULL,
            room_id bigint(20) unsigned DEFAULT 0 NOT NULL,
            room_name_snapshot varchar(255) DEFAULT '' NOT NULL,
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_phone varchar(50) DEFAULT '' NOT NULL,
            check_in date NOT NULL,
            check_out date NOT NULL,
            guests int(11) DEFAULT 1 NOT NULL,
            adults int(11) DEFAULT 1 NOT NULL,
            children int(11) DEFAULT 0 NOT NULL,
            total_price decimal(10,2) NOT NULL,
            price_snapshot text NOT NULL,
            cleaning_fee decimal(10,2) DEFAULT 0 NOT NULL,
            extra_guest_fee decimal(10,2) DEFAULT 0 NOT NULL,
            tax_amount decimal(10,2) DEFAULT 0 NOT NULL,
            admin_commission decimal(10,2) DEFAULT 0 NOT NULL,
            host_payout decimal(10,2) DEFAULT 0 NOT NULL,
            deposit_amount decimal(10,2) DEFAULT 0 NOT NULL,
            balance_due decimal(10,2) DEFAULT 0 NOT NULL,
            status varchar(50) DEFAULT 'pending_inquiry' NOT NULL,
            payment_token varchar(255) DEFAULT '' NOT NULL,
            payment_expires_at datetime DEFAULT NULL,
            gateway varchar(50) DEFAULT '' NOT NULL,
            transaction_id varchar(255) DEFAULT '' NOT NULL,
            refund_id varchar(255) DEFAULT '' NOT NULL,
            refund_amount decimal(10,2) DEFAULT 0 NOT NULL,
            refund_status varchar(50) DEFAULT '' NOT NULL,
            refunded_at datetime DEFAULT NULL,
            invoice_number varchar(20) DEFAULT '' NOT NULL,
            notes text DEFAULT '' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY homestay_id (homestay_id),
            KEY room_id (room_id),
            KEY dates (check_in, check_out),
            KEY status (status),
            KEY payment_expires_at (payment_expires_at),
            KEY idx_availability (room_id, status, check_in, check_out, payment_expires_at),
            UNIQUE KEY token (payment_token)
        ) $collate;";

        // =====================================================================
        // 2. Temporary Date Locks (existing)
        // =====================================================================
        $table_holds = $wpdb->prefix . 'himalayan_booking_hold';
        $sql2 = "CREATE TABLE $table_holds (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            homestay_id bigint(20) unsigned NOT NULL,
            room_id bigint(20) unsigned DEFAULT 0 NOT NULL,
            session_id varchar(255) NOT NULL,
            check_in date NOT NULL,
            check_out date NOT NULL,
            quantity int(11) DEFAULT 1 NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY homestay_id (homestay_id),
            KEY room_id (room_id),
            KEY expires_at (expires_at),
            UNIQUE KEY hold_session (room_id, session_id)
        ) $collate;";

        // =====================================================================
        // 3. Pricing Rules (existing)
        // =====================================================================
        $table_pricing = $wpdb->prefix . 'himalayan_pricing_rules';
        $sql3 = "CREATE TABLE $table_pricing (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            homestay_id bigint(20) unsigned NOT NULL,
            rule_type varchar(50) NOT NULL,
            start_date date DEFAULT NULL,
            end_date date DEFAULT NULL,
            modifier_type varchar(20) NOT NULL,
            value decimal(10,2) NOT NULL,
            days_of_week varchar(50) DEFAULT '' NOT NULL,
            priority int(11) DEFAULT 10 NOT NULL,
            stackable tinyint(1) DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id),
            KEY homestay_id (homestay_id)
        ) $collate;";

        // =====================================================================
        // 4. Extra Services / Add-ons (NEW)
        // =====================================================================
        $table_services = $wpdb->prefix . 'himalayan_extra_services';
        $sql4 = "CREATE TABLE $table_services (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            homestay_id bigint(20) unsigned DEFAULT 0 NOT NULL,
            service_name varchar(255) NOT NULL,
            description text DEFAULT '' NOT NULL,
            price decimal(10,2) NOT NULL,
            price_type varchar(20) DEFAULT 'flat' NOT NULL,
            is_active tinyint(1) DEFAULT 1 NOT NULL,
            sort_order int(11) DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id),
            KEY homestay_id (homestay_id)
        ) $collate;";

        // =====================================================================
        // 5. Booking ↔ Services Pivot Table (NEW)
        // =====================================================================
        $table_booking_services = $wpdb->prefix . 'himalayan_booking_services';
        $sql5 = "CREATE TABLE $table_booking_services (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned NOT NULL,
            service_id bigint(20) unsigned NOT NULL,
            quantity int(11) DEFAULT 1 NOT NULL,
            unit_price decimal(10,2) NOT NULL,
            subtotal decimal(10,2) NOT NULL,
            PRIMARY KEY  (id),
            KEY booking_id (booking_id),
            KEY service_id (service_id)
        ) $collate;";

        // =====================================================================
        // 6. Email Log (NEW)
        // =====================================================================
        $table_email_log = $wpdb->prefix . 'himalayan_email_log';
        $sql6 = "CREATE TABLE $table_email_log (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned NOT NULL,
            email_type varchar(50) NOT NULL,
            recipient varchar(255) NOT NULL,
            subject varchar(255) NOT NULL,
            sent_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            status varchar(20) DEFAULT 'sent' NOT NULL,
            PRIMARY KEY  (id),
            KEY booking_id (booking_id),
            UNIQUE KEY idempotent_email (booking_id, email_type)
        ) $collate;";

        // =====================================================================
        // 6b. Payment Events Ledger (NEW for Events & Idempotency)
        // =====================================================================
        $table_payment_events = $wpdb->prefix . 'himalayan_payment_events';
        $sql6b = "CREATE TABLE $table_payment_events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned NOT NULL,
            gateway varchar(50) NOT NULL,
            transaction_id varchar(255) NOT NULL,
            event_type varchar(100) NOT NULL,
            amount decimal(10,2) NOT NULL,
            payload text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY idempotency_key (gateway, transaction_id, event_type)
        ) $collate;";

        // =====================================================================
        // 6c. Invoice Sequence Generator (NEW for Phase 1 Hardening)
        // =====================================================================
        $table_invoice_seq = $wpdb->prefix . 'himalayan_invoice_sequences';
        $sql6c = "CREATE TABLE $table_invoice_seq (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            last_invoice_number bigint(20) unsigned DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id)
        ) $collate;";

        // =====================================================================
        // 7. Verified Guest Reviews (NEW for Phase 12)
        // =====================================================================
        $table_reviews = $wpdb->prefix . 'hhb_reviews';
        $sql7 = "CREATE TABLE $table_reviews (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned NOT NULL,
            homestay_id bigint(20) unsigned NOT NULL,
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            rating int(1) NOT NULL,
            rating_cleanliness int(1) DEFAULT 0 NOT NULL,
            rating_communication int(1) DEFAULT 0 NOT NULL,
            rating_location int(1) DEFAULT 0 NOT NULL,
            rating_value int(1) DEFAULT 0 NOT NULL,
            comment text NOT NULL,
            status varchar(20) DEFAULT 'approved' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY booking_id (booking_id),
            KEY homestay_id (homestay_id),
            UNIQUE KEY unique_booking_review (booking_id)
        ) $collate;";

        // =====================================================================
        // 8. External iCal Feeds (NEW for Phase 6)
        // =====================================================================
        $table_ical_feeds = $wpdb->prefix . 'hhb_ical_feeds';
        $sql8 = "CREATE TABLE $table_ical_feeds (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            homestay_id bigint(20) unsigned NOT NULL,
            source_name varchar(100) NOT NULL,
            feed_url varchar(1000) NOT NULL,
            last_synced datetime DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY homestay_id (homestay_id)
        ) $collate;";

        // =====================================================================
        // 9. Discount Coupons (NEW for Phase 7)
        // =====================================================================
        $table_coupons = $wpdb->prefix . 'himalayan_coupons';
        $sql9 = "CREATE TABLE $table_coupons (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL,
            discount_type varchar(20) DEFAULT 'percent' NOT NULL,
            discount_value decimal(10,2) NOT NULL,
            max_uses int(11) DEFAULT 0 NOT NULL,
            used_count int(11) DEFAULT 0 NOT NULL,
            valid_from datetime DEFAULT NULL,
            valid_to datetime DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_code (code)
        ) $collate;";

        // =====================================================================
        // 10. Audit Log (Status Transition History)
        // =====================================================================
        $table_audit = $wpdb->prefix . 'himalayan_audit_log';
        $sql10 = "CREATE TABLE $table_audit (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned NOT NULL,
            old_status varchar(50) NOT NULL,
            new_status varchar(50) NOT NULL,
            actor varchar(100) DEFAULT 'system' NOT NULL,
            note text DEFAULT '' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY booking_id (booking_id)
        ) $collate;";

        // =====================================================================
        // 11. Host Payouts Ledger
        // =====================================================================
        $table_payouts = $wpdb->prefix . 'himalayan_payouts';
        $sql11 = "CREATE TABLE $table_payouts (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned NOT NULL,
            host_id bigint(20) unsigned NOT NULL,
            homestay_id bigint(20) unsigned NOT NULL,
            total_amount decimal(10,2) NOT NULL DEFAULT 0,
            commission_amount decimal(10,2) NOT NULL DEFAULT 0,
            host_payout_amount decimal(10,2) NOT NULL DEFAULT 0,
            payout_status varchar(20) DEFAULT 'pending' NOT NULL,
            paid_at datetime DEFAULT NULL,
            paid_by bigint(20) unsigned DEFAULT NULL,
            notes text DEFAULT '' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY booking_id (booking_id),
            KEY host_id (host_id),
            KEY payout_status (payout_status),
            UNIQUE KEY unique_booking_payout (booking_id)
        ) $collate;";

        // =====================================================================
        // 12. Room Availability Ledger (Replaces old ledger format)
        // =====================================================================
        $table_room_availability = $wpdb->prefix . 'himalayan_room_availability';
        $sql12 = "CREATE TABLE $table_room_availability (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            room_id bigint(20) unsigned NOT NULL,
            date date NOT NULL,
            status varchar(50) NOT NULL,
            price_override decimal(10,2) DEFAULT NULL,
            quantity_available int(11) DEFAULT 1 NOT NULL,
            PRIMARY KEY  (id),
            KEY room_date (room_id, date),
            UNIQUE KEY unique_room_date (room_id, date)
        ) $collate;";

        // =====================================================================
        // 13. Bookings Archive Ledger (NEW for Phase 6 Architecture)
        // =====================================================================
        $table_archive = $wpdb->prefix . 'himalayan_bookings_archive';
        $sql13 = "CREATE TABLE $table_archive (
            id bigint(20) unsigned NOT NULL,
            homestay_id bigint(20) unsigned NOT NULL,
            room_id bigint(20) unsigned DEFAULT 0 NOT NULL,
            room_name_snapshot varchar(255) DEFAULT '' NOT NULL,
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_phone varchar(50) DEFAULT '' NOT NULL,
            check_in date NOT NULL,
            check_out date NOT NULL,
            guests int(11) DEFAULT 1 NOT NULL,
            adults int(11) DEFAULT 1 NOT NULL,
            children int(11) DEFAULT 0 NOT NULL,
            total_price decimal(10,2) NOT NULL,
            price_snapshot text NOT NULL,
            cleaning_fee decimal(10,2) DEFAULT 0 NOT NULL,
            extra_guest_fee decimal(10,2) DEFAULT 0 NOT NULL,
            tax_amount decimal(10,2) DEFAULT 0 NOT NULL,
            admin_commission decimal(10,2) DEFAULT 0 NOT NULL,
            host_payout decimal(10,2) DEFAULT 0 NOT NULL,
            deposit_amount decimal(10,2) DEFAULT 0 NOT NULL,
            balance_due decimal(10,2) DEFAULT 0 NOT NULL,
            status varchar(50) NOT NULL,
            payment_token varchar(255) DEFAULT '' NOT NULL,
            payment_expires_at datetime DEFAULT NULL,
            gateway varchar(50) DEFAULT '' NOT NULL,
            transaction_id varchar(255) DEFAULT '' NOT NULL,
            refund_id varchar(255) DEFAULT '' NOT NULL,
            refund_amount decimal(10,2) DEFAULT 0 NOT NULL,
            refund_status varchar(50) DEFAULT '' NOT NULL,
            refunded_at datetime DEFAULT NULL,
            invoice_number varchar(20) DEFAULT '' NOT NULL,
            notes text DEFAULT '' NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            archived_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY homestay_id (homestay_id),
            KEY room_id (room_id),
            KEY dates (check_in, check_out)
        ) $collate;";

        dbDelta( $sql1 );
        dbDelta( $sql2 );
        dbDelta( $sql3 );
        dbDelta( $sql4 );
        dbDelta( $sql5 );
        dbDelta( $sql6 );
        dbDelta( $sql6b );
        dbDelta( $sql6c );
        dbDelta( $sql7 );
        dbDelta( $sql8 );
        dbDelta( $sql9 );
        dbDelta( $sql10 );
        dbDelta( $sql11 );
        dbDelta( $sql12 );
        dbDelta( $sql13 );

        // ── Enterprise Compliance: Audit Log Immutability Triggers ───────
        $wpdb->query( "DROP TRIGGER IF EXISTS prevent_audit_log_update" );
        $wpdb->query( "
            CREATE TRIGGER prevent_audit_log_update 
            BEFORE UPDATE ON {$table_audit} 
            FOR EACH ROW 
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HHB Enterprise Guard: Audit log records are immutable and cannot be updated.'
        " );

        $wpdb->query( "DROP TRIGGER IF EXISTS prevent_audit_log_delete" );
        $wpdb->query( "
            CREATE TRIGGER prevent_audit_log_delete 
            BEFORE DELETE ON {$table_audit} 
            FOR EACH ROW 
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HHB Enterprise Guard: Audit log records are immutable and cannot be deleted.'
        " );

        // Seed invoice sequence row if not exists.
        $seq_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_invoice_seq" );
        if ( ! $seq_count ) {
            $wpdb->insert( $table_invoice_seq, [ 'last_invoice_number' => 0 ] );
        }

        // Update schema version.
        update_option( 'hhb_db_version', self::DB_VERSION );

        // Register custom roles (NEW for Phase 8)
        self::register_roles();

        // Schedule cleanup cron if not scheduled.
        if ( ! wp_next_scheduled( 'himalayan_cleanup_expired_holds' ) ) {
            wp_schedule_event( time(), 'five_minutes', 'himalayan_cleanup_expired_holds' );
        }

        // Schedule payment expiry checker (every 5 minutes).
        if ( ! wp_next_scheduled( 'hhb_check_payment_expiry' ) ) {
            wp_schedule_event( time(), 'five_minutes', 'hhb_check_payment_expiry' );
        }

        // Schedule iCal sync cron if not scheduled.
        if ( ! wp_next_scheduled( 'hhb_sync_ical_feeds' ) ) {
            wp_schedule_event( time(), 'fifteen_minutes', 'hhb_sync_ical_feeds' );
        }
    }

    private static function register_roles() {
        // Add Host role
        add_role(
            'hhb_host',
            __( 'Host', 'himalayan-homestay-bookings' ),
            array(
                'read'                => true,
                'edit_posts'          => true,   // Allow them to edit their own posts
                'upload_files'        => true,   // Allow uploading property images
                'manage_hhb_property' => true,   // Custom capability for API access
            )
        );
    }
}
