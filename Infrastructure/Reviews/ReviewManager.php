<?php
/**
 * Review Manager
 *
 * Handles automated cron tasks to request reviews from checked-out guests.
 *
 * @package Himalayan\Homestay\Infrastructure\Reviews
 */

namespace Himalayan\Homestay\Infrastructure\Reviews;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ReviewManager {

    public static function init() {
        // Run daily cron event hook
        add_action( 'hhb_daily_review_requests', [ __CLASS__, 'process_review_requests' ] );
        
        // Ensure cron is scheduled
        if ( ! wp_next_scheduled( 'hhb_daily_review_requests' ) ) {
            wp_schedule_event( strtotime( '10:00:00' ), 'daily', 'hhb_daily_review_requests' );
        }
    }

    public static function process_review_requests() {
        global $wpdb;
        $table_bookings  = $wpdb->prefix . 'himalayan_bookings';
        $table_email_log = $wpdb->prefix . 'himalayan_email_log';
        $table_reviews   = $wpdb->prefix . 'hhb_reviews';

        // Find bookings that are 'confirmed', checked out in the past 14 days,
        // and do not have an existing review, AND haven't been sent a 'review_request' email.
        $fourteen_days_ago = date( 'Y-m-d', strtotime( '-14 days' ) );
        $yesterday         = date( 'Y-m-d', strtotime( '-1 day' ) );

        $query = $wpdb->prepare("
            SELECT b.id, b.homestay_id, b.customer_name, b.customer_email
            FROM $table_bookings b
            LEFT JOIN $table_email_log e ON b.id = e.booking_id AND e.email_type = 'review_request'
            LEFT JOIN $table_reviews r ON b.id = r.booking_id
            WHERE b.status = 'confirmed'
              AND b.check_out >= %s 
              AND b.check_out <= %s
              AND e.id IS NULL
              AND r.id IS NULL
            LIMIT 50
        ", $fourteen_days_ago, $yesterday);

        $bookings = $wpdb->get_results( $query );

        if ( empty( $bookings ) ) {
            return;
        }

        foreach ( $bookings as $booking ) {
            // Generate a secure token for the review link
            $token = md5( $booking->id . $booking->customer_email . wp_salt() );
            $review_url = add_query_arg( [
                'hhb_review' => '1',
                'booking_id' => $booking->id,
                'token'      => $token
            ], home_url( '/' ) );

            // Send Email via EmailNotifier
            \Himalayan\Homestay\Infrastructure\Notifications\EmailNotifier::send_review_request( $booking, $review_url );
        }
    }
}
