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
    const DB_VERSION = '2.0.0';

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
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_phone varchar(50) DEFAULT '' NOT NULL,
            check_in date NOT NULL,
            check_out date NOT NULL,
            guests int(11) DEFAULT 1 NOT NULL,
            adults int(11) DEFAULT 1 NOT NULL,
            children int(11) DEFAULT 0 NOT NULL,
            total_price decimal(10,2) NOT NULL,
            deposit_amount decimal(10,2) DEFAULT 0 NOT NULL,
            balance_due decimal(10,2) DEFAULT 0 NOT NULL,
            status varchar(50) DEFAULT 'pending_inquiry' NOT NULL,
            payment_token varchar(255) DEFAULT '' NOT NULL,
            gateway varchar(50) DEFAULT '' NOT NULL,
            transaction_id varchar(255) DEFAULT '' NOT NULL,
            notes text DEFAULT '' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY homestay_id (homestay_id),
            KEY dates (check_in, check_out),
            KEY status (status),
            UNIQUE KEY token (payment_token)
        ) $collate;";

        // =====================================================================
        // 2. Temporary Date Locks (existing)
        // =====================================================================
        $table_holds = $wpdb->prefix . 'himalayan_booking_hold';
        $sql2 = "CREATE TABLE $table_holds (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            homestay_id bigint(20) unsigned NOT NULL,
            session_id varchar(255) NOT NULL,
            check_in date NOT NULL,
            check_out date NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY homestay_id (homestay_id),
            KEY expires_at (expires_at),
            UNIQUE KEY hold_session (homestay_id, session_id)
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
            KEY booking_id (booking_id)
        ) $collate;";

        dbDelta( $sql1 );
        dbDelta( $sql2 );
        dbDelta( $sql3 );
        dbDelta( $sql4 );
        dbDelta( $sql5 );
        dbDelta( $sql6 );

        // Update schema version.
        update_option( 'hhb_db_version', self::DB_VERSION );

        // Schedule cleanup cron if not scheduled.
        if ( ! wp_next_scheduled( 'himalayan_cleanup_expired_holds' ) ) {
            wp_schedule_event( time(), 'five_minutes', 'himalayan_cleanup_expired_holds' );
        }
    }
}
