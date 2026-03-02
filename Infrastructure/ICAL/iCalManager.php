<?php
/**
 * iCalendar Manager
 *
 * Handles generating .ics feeds for Himalayan Homestays (Export)
 * and fetching/parsing external .ics feeds (Import).
 *
 * @package Himalayan\Homestay\Infrastructure\ICAL
 */

namespace Himalayan\Homestay\Infrastructure\ICAL;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class iCalManager {

    public static function init(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_export_endpoint' ] );
        // Cron action for sync
        add_action( 'hhb_sync_ical_feeds', [ __CLASS__, 'sync_all_external_feeds' ] );
    }

    /**
     * Expose a REST route for downloading a homestay's calendar.
     */
    public static function register_export_endpoint(): void {
        register_rest_route( 'hhb/v1', '/ical/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'generate_ical_feed' ],
            'permission_callback' => '__return_true', // Public feed for Airbnb/Booking.com
        ] );
    }

    /**
     * Generates standard VCALENDAR format from homestay bookings and holds.
     */
    public static function generate_ical_feed( \WP_REST_Request $request ) {
        $homestay_id = (int) $request->get_param( 'id' );

        if ( 'hhb_homestay' !== get_post_type( $homestay_id ) ) {
            return new \WP_REST_Response( 'Invalid Homestay ID', 404 );
        }

        global $wpdb;
        $bookings_table = $wpdb->prefix . 'himalayan_bookings';
        $holds_table    = $wpdb->prefix . 'himalayan_booking_hold';

        // Get confirmed/pending bookings
        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, check_in, check_out, status FROM {$bookings_table} 
             WHERE homestay_id = %d AND status NOT IN ('cancelled', 'failed', 'refunded')",
            $homestay_id
        ) );

        // Get active holds
        $holds = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, check_in, check_out, session_id FROM {$holds_table} 
             WHERE homestay_id = %d AND expires_at > NOW()",
            $homestay_id
        ) );

        $site_name = get_bloginfo( 'name' );
        $post_name = get_the_title( $homestay_id );

        // Build iCal Header
        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "PRODID:-//" . $site_name . "//Himalayan Homestay Bookings//EN\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";
        $ical .= "X-WR-CALNAME:" . $site_name . " - " . $post_name . "\r\n";
        $ical .= "X-PUBLISHED-TTL:PT15M\r\n";

        // Add Bookings
        if ( ! empty( $bookings ) ) {
            foreach ( $bookings as $b ) {
                $ical .= "BEGIN:VEVENT\r\n";
                $ical .= "DTSTART;VALUE=DATE:" . gmdate( 'Ymd', strtotime( $b->check_in ) ) . "\r\n";
                // iCal DTEND is exclusive, so it represents the checkout date perfectly
                $ical .= "DTEND;VALUE=DATE:" . gmdate( 'Ymd', strtotime( $b->check_out ) ) . "\r\n";
                $ical .= "UID:booking-{$b->id}-" . md5( $site_name ) . "\r\n";
                $ical .= "DTSTAMP:" . gmdate( 'Ymd\THis\Z' ) . "\r\n";
                $ical .= "SUMMARY:Reserved ({$b->status})\r\n";
                $ical .= "END:VEVENT\r\n";
            }
        }

        // Add Holds
        if ( ! empty( $holds ) ) {
            foreach ( $holds as $h ) {
                $ical .= "BEGIN:VEVENT\r\n";
                $ical .= "DTSTART;VALUE=DATE:" . gmdate( 'Ymd', strtotime( $h->check_in ) ) . "\r\n";
                $ical .= "DTEND;VALUE=DATE:" . gmdate( 'Ymd', strtotime( $h->check_out ) ) . "\r\n";
                $ical .= "UID:hold-{$h->id}-" . md5( $site_name ) . "\r\n";
                $ical .= "DTSTAMP:" . gmdate( 'Ymd\THis\Z' ) . "\r\n";
                $ical .= "SUMMARY:Pending Hold\r\n";
                $ical .= "END:VEVENT\r\n";
            }
        }

        $ical .= "END:VCALENDAR";

        // Return as plain text rendering of icalendar
        header('Content-type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="homestay-' . $homestay_id . '.ics"');
        echo $ical;
        exit;
    }

    /**
     * Cron callback: Loop all active feeds and sync them into our `hhb_bookings` table.
     */
    public static function sync_all_external_feeds(): void {
        global $wpdb;
        $feeds_table = $wpdb->prefix . 'hhb_ical_feeds';
        $bookings_table = $wpdb->prefix . 'himalayan_bookings';

        // Get all active feeds
        $suppress = $wpdb->suppress_errors();
        $feeds = $wpdb->get_results( "SELECT * FROM {$feeds_table} WHERE is_active = 1" );
        $wpdb->suppress_errors( $suppress );

        // Record last-run timestamp for the Cron & Automation admin tab.
        update_option( 'hhb_cron_last_ran_hhb_sync_ical_feeds', time() );

        if ( empty( $feeds ) ) {
            return;
        }

        foreach ( $feeds as $feed ) {
            $response = wp_remote_get( $feed->feed_url, array( 'timeout' => 15 ) );
            if ( is_wp_error( $response ) ) {
                continue;
            }

            $body = wp_remote_retrieve_body( $response );
            if ( empty( $body ) || strpos( $body, 'BEGIN:VCALENDAR' ) === false ) {
                continue;
            }

            // Parse the iCal body
            $events = self::parse_ical( $body );

            if ( ! empty( $events ) ) {
                // 1. Clear existing synced future events for this specific feed 
                // We must also remove them from the availability ledger first.
                $booking_ids_to_delete = $wpdb->get_col( $wpdb->prepare(
                    "SELECT id FROM {$bookings_table} WHERE homestay_id = %d AND status = 'ical_sync' AND transaction_id = %s",
                    $feed->homestay_id, 'ical_' . $feed->id
                ) );

                if ( ! empty( $booking_ids_to_delete ) ) {
                    $ids_list = implode( ',', array_map( 'intval', $booking_ids_to_delete ) );
                    $wpdb->query( "DELETE FROM {$wpdb->prefix}himalayan_availability_ledger WHERE booking_id IN ({$ids_list})" );
                    $wpdb->query( "DELETE FROM {$bookings_table} WHERE id IN ({$ids_list})" );
                }

                // 2. Insert fresh parsed events ATOMICALLY
                $ledger_table = $wpdb->prefix . 'himalayan_availability_ledger';

                foreach ( $events as $event ) {
                    $start_dt = new \DateTime( $event['start'] );
                    $end_dt   = new \DateTime( $event['end'] );
                    $period   = new \DatePeriod( $start_dt, new \DateInterval( 'P1D' ), $end_dt );
                    
                    $dates_to_check = [];
                    foreach ( $period as $dt ) {
                        $dates_to_check[] = $dt->format( 'Y-m-d' );
                    }

                    if ( empty( $dates_to_check ) ) {
                        continue;
                    }

                    // Build atomic condition matching the ledger
                    $placeholders = implode( ',', array_fill( 0, count( $dates_to_check ), '%s' ) );
                    $payment_token = wp_generate_password( 24, false );

                    $query_args = [
                        // INSERT params
                        $feed->homestay_id, 
                        $feed->source_name . ' (' . __( 'External', 'himalayan-homestay-bookings' ) . ')',
                        'ical@' . sanitize_title( $feed->source_name ) . '.local',
                        $event['start'],
                        $event['end'],
                        'ical_sync',
                        $payment_token,
                        'None',
                        'ical_' . $feed->id,
                        0,
                        // WHERE NOT EXISTS params
                        $feed->homestay_id
                    ];
                    $query_args = array_merge( $query_args, $dates_to_check );

                    $insert_sql = $wpdb->prepare( "
                        INSERT INTO {$bookings_table} 
                        (homestay_id, customer_name, customer_email, check_in, check_out, status, payment_token, gateway, transaction_id, total_price)
                        SELECT %d, %s, %s, %s, %s, %s, %s, %s, %s, %d
                        FROM DUAL
                        WHERE NOT EXISTS (
                            SELECT 1 FROM {$ledger_table}
                            WHERE homestay_id = %d AND date IN ({$placeholders})
                        )
                    ", ...$query_args );

                    $wpdb->query( $insert_sql );
                    $new_booking_id = $wpdb->insert_id;

                    // If the insert succeeded (i.e. no overlap), populate the ledger.
                    if ( $new_booking_id ) {
                        foreach ( $dates_to_check as $date_str ) {
                            $wpdb->query( $wpdb->prepare(
                                "INSERT IGNORE INTO {$ledger_table} (homestay_id, booking_id, date, status) VALUES (%d, %d, %s, %s)",
                                $feed->homestay_id, $new_booking_id, $date_str, 'ical_sync'
                            ) );
                        }
                    } else {
                        error_log( sprintf( 'HHB iCal Sync: Race condition averted for Homestay #%d. External dates %s to %s overlapped with real ledger bookings.', $feed->homestay_id, $event['start'], $event['end'] ) );
                    }
                }
            }

            // Update last_synced
            $wpdb->update(
                $feeds_table,
                [ 'last_synced' => current_time( 'mysql' ) ],
                [ 'id' => $feed->id ]
            );
        }
    }

    /**
     * Helper to parse VEVENT blocks out of raw iCal text.
     */
    private static function parse_ical( string $ical_data ): array {
        $events = [];
        $lines = explode( "\n", $ical_data );
        $in_event = false;
        $current_event = [];

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) ) continue;

            if ( $line === 'BEGIN:VEVENT' ) {
                $in_event = true;
                $current_event = [];
                continue;
            }

            if ( $line === 'END:VEVENT' ) {
                $in_event = false;
                // Standard iCal DTEND is exclusive, so it represents check_out perfectly.
                if ( isset( $current_event['start'] ) && isset( $current_event['end'] ) ) {
                    // Only import events that end in the future to save space
                    if ( strtotime( $current_event['end'] ) >= strtotime( 'today' ) ) {
                        $events[] = $current_event;
                    }
                }
                continue;
            }

            if ( $in_event ) {
                if ( strncmp( $line, 'DTSTART', 7 ) === 0 ) {
                    $date = self::extract_ical_date( $line );
                    if ( $date ) $current_event['start'] = $date;
                } elseif ( strncmp( $line, 'DTEND', 5 ) === 0 ) {
                    $date = self::extract_ical_date( $line );
                    if ( $date ) $current_event['end'] = $date;
                }
            }
        }
        return $events;
    }

    /**
     * Extracts Y-m-d from DTSTART/DTEND lines format: DTSTART;VALUE=DATE:20250601
     */
    private static function extract_ical_date( string $line ): ?string {
        $parts = explode( ':', $line );
        if ( count( $parts ) >= 2 ) {
            $date_str = trim( end( $parts ) );
            if ( preg_match( '/^(\d{4})(\d{2})(\d{2})/', $date_str, $matches ) ) {
                return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
            }
        }
        return null;
    }
}
