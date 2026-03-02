<?php
/**
 * Email Automator
 *
 * Integrates with WP-Cron to dispatch scheduled marketing and lifecycle emails
 * like pre-arrival info, post-checkout reviews, and win-back campaigns.
 *
 * @package Himalayan\Homestay\Infrastructure\Notifications
 */

namespace Himalayan\Homestay\Infrastructure\Notifications;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EmailAutomator {

    public static function init(): void {
        // Schedule event if not already
        if ( ! wp_next_scheduled( 'hhb_daily_email_automator' ) ) {
            wp_schedule_event( time(), 'daily', 'hhb_daily_email_automator' );
        }
        
        // Schedule frequent event for dropped bookings
        if ( ! wp_next_scheduled( 'hhb_fifteen_minute_automator' ) ) {
            wp_schedule_event( time(), 'fifteen_minutes', 'hhb_fifteen_minute_automator' );
        }

        // Hook into events
        add_action( 'hhb_daily_email_automator', [ __CLASS__, 'process_automated_emails' ] );
        add_action( 'hhb_fifteen_minute_automator', [ __CLASS__, 'process_frequent_tasks' ] );
    }

    /**
     * Entry point for WP-Cron to run all sequence builders.
     */
    public static function process_automated_emails(): void {
        self::send_pre_arrival_emails();
        self::send_post_checkout_emails();
        self::send_review_followup_emails();
        self::send_win_back_primary();
        self::send_win_back_secondary();
        self::archive_stale_cash_bookings();
        // Record last-run timestamp for the Cron & Automation admin tab.
        update_option( 'hhb_cron_last_ran_hhb_daily_email_automator', time() );
    }

    /**
     * Entry point for frequent tasks (like checking dropped bookings).
     */
    public static function process_frequent_tasks(): void {
        self::check_dropped_bookings();
        update_option( 'hhb_cron_last_ran_hhb_fifteen_minute_automator', time() );
    }

    /**
     * Check for dropped bookings (pending + payment_expires_at < NOW())
     * Routes through BookingManager::transition_status() for audit + hooks.
     */
    private static function check_dropped_bookings(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_bookings';
        
        $bookings = $wpdb->get_results( "SELECT id FROM {$table} WHERE status = 'pending' AND payment_expires_at IS NOT NULL AND payment_expires_at < NOW()" );
        
        $manager = new \Himalayan\Homestay\Domain\Booking\BookingManager();
        foreach ( $bookings as $booking ) {
            try {
                $manager->drop_booking( $booking->id );
            } catch ( \Exception $e ) {
                error_log( 'HHB Cron Drop Error: ' . $e->getMessage() );
            }
        }
    }

    /**
     * Archive stale cash bookings older than 7 days.
     * Cash bookings remain "pending" indefinitely since there's no payment expiry.
     * This cron frees up the dates by transitioning them to "dropped".
     */
    private static function archive_stale_cash_bookings(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_bookings';

        $stale = $wpdb->get_results( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE status = 'pending' AND payment_expires_at IS NULL AND gateway = 'Cash' AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            7
        ) );

        $manager = new \Himalayan\Homestay\Domain\Booking\BookingManager();
        foreach ( $stale as $booking ) {
            try {
                $manager->drop_booking( $booking->id );
                error_log( sprintf( 'HHB Stale Archive: Cash booking #%d dropped (>7 days pending).', $booking->id ) );
            } catch ( \Exception $e ) {
                error_log( 'HHB Stale Archive Error: ' . $e->getMessage() );
            }
        }
    }

    /**
     * Pre-Arrival Sequence (3 days before check-in)
     */
    private static function send_pre_arrival_emails(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_bookings';
        
        // Find bookings checking in 3 days from now
        $target_date = date('Y-m-d', strtotime('+3 days'));
        
        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE DATE(check_in) = %s AND status IN ('approved', 'confirmed')",
            $target_date
        ) );

        foreach ( $bookings as $booking ) {
            if ( self::has_been_sent($booking->id, 'pre_arrival') ) continue;

            $homestay = get_post( $booking->homestay_id );
            $hs_title = $homestay ? $homestay->post_title : 'Homestay';

            $options = get_option( \Himalayan\Homestay\Interface\Admin\SettingsPage::OPTION_KEY, [] );
            
            $placeholders = ['{guest_name}', '{property_name}', '{check_in}', '{check_out}', '{total_price}', '{booking_id}'];
            $replacements = [
                $booking->customer_name,
                $hs_title,
                date_i18n( get_option( 'date_format' ), strtotime( $booking->check_in ) ),
                date_i18n( get_option( 'date_format' ), strtotime( $booking->check_out ) ),
                number_format( (float) $booking->total_price, 2 ),
                $booking->id
            ];

            $default_subject = sprintf( __( 'Getting Ready: Your Stay at %s', 'himalayan-homestay-bookings' ), $hs_title );
            $subject = ! empty( $options['email_subject_pre_arrival'] ) 
                ? str_replace( $placeholders, $replacements, $options['email_subject_pre_arrival'] ) 
                : $default_subject;
                
            $default_body = sprintf(
                __( 'Hi %s, we are excited to host you at <strong>%s</strong> in just 3 days! Please review your check-in instructions so you are fully prepared for your journey into the Himalayas.', 'himalayan-homestay-bookings' ),
                esc_html( $booking->customer_name ),
                esc_html( $hs_title )
            );
            $body = ! empty( $options['email_body_pre_arrival'] ) 
                ? wp_kses_post( str_replace( $placeholders, $replacements, nl2br($options['email_body_pre_arrival']) ) )
                : $default_body;

            EmailNotifier::send_and_log( $booking->id, 'pre_arrival', $booking->customer_email, $subject,
                EmailNotifier::build_html([
                    'heading' => __( 'Your trip is almost here!', 'himalayan-homestay-bookings' ),
                    'message' => $body,
                    'details' => EmailNotifier::booking_details_table( $booking, $hs_title ),
                    'footer'  => __( 'If you have any last-minute questions, please reply directly to this email.', 'himalayan-homestay-bookings' ),
                ])
            );
        }
    }

    /**
     * Post-Checkout Sequence (1 day after checkout)
     */
    private static function send_post_checkout_emails(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_bookings';
        
        // Find bookings checked out 1 day ago
        $target_date = date('Y-m-d', strtotime('-1 days'));
        
        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE DATE(check_out) = %s AND status IN ('approved', 'confirmed')",
            $target_date
        ) );

        foreach ( $bookings as $booking ) {
            if ( self::has_been_sent($booking->id, 'post_checkout') ) continue;

            $homestay = get_post( $booking->homestay_id );
            $hs_title = $homestay ? $homestay->post_title : 'Homestay';
            
            // Generate a tokenized link to Phase 13 Review form handler
            $review_link = site_url('/leave-review/?booking_id=' . $booking->id . '&token=' . wp_create_nonce('hhb_review_'.$booking->id));

            $options = get_option( \Himalayan\Homestay\Interface\Admin\SettingsPage::OPTION_KEY, [] );
            
            $placeholders = ['{guest_name}', '{property_name}', '{check_in}', '{check_out}', '{total_price}', '{booking_id}'];
            $replacements = [
                $booking->customer_name,
                $hs_title,
                date_i18n( get_option( 'date_format' ), strtotime( $booking->check_in ) ),
                date_i18n( get_option( 'date_format' ), strtotime( $booking->check_out ) ),
                number_format( (float) $booking->total_price, 2 ),
                $booking->id
            ];

            $default_subject = sprintf( __( 'How was your stay at %s?', 'himalayan-homestay-bookings' ), $hs_title );
            $subject = ! empty( $options['email_subject_post_checkout'] ) 
                ? str_replace( $placeholders, $replacements, $options['email_subject_post_checkout'] ) 
                : $default_subject;
                
            $default_body = sprintf(
                __( 'Hi %s, we hope you had a wonderful time at <strong>%s</strong> and a safe journey back home. Your feedback helps our mountain community thrive!', 'himalayan-homestay-bookings' ),
                esc_html( $booking->customer_name ),
                esc_html( $hs_title )
            );
            $body = ! empty( $options['email_body_post_checkout'] ) 
                ? wp_kses_post( str_replace( $placeholders, $replacements, nl2br($options['email_body_post_checkout']) ) )
                : $default_body;

            EmailNotifier::send_and_log( $booking->id, 'post_checkout', $booking->customer_email, $subject,
                EmailNotifier::build_html([
                    'heading' => __( 'Thank you for staying with us!', 'himalayan-homestay-bookings' ),
                    'message' => $body,
                    'cta_url'  => $review_link,
                    'cta_text' => __( 'Leave a Review', 'himalayan-homestay-bookings' ),
                    'footer'  => __( 'It only takes a minute to share your experience.', 'himalayan-homestay-bookings' ),
                ])
            );
        }
    }

    /**
     * Review Follow-up Sequence (5 days after checkout — 4 days after first review email)
     * Only fires if initial post_checkout email was sent AND follow-up hasn't been sent yet.
     */
    private static function send_review_followup_emails(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_bookings';

        // Target: checked out N days ago (user-configurable, default 5).
        $opts        = get_option( \Himalayan\Homestay\Interface\Admin\SettingsPage::OPTION_KEY, [] );
        $followup_days = max( 1, intval( $opts['review_followup_days'] ?? 5 ) );
        $target_date = date( 'Y-m-d', strtotime( "-{$followup_days} days" ) );

        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE DATE(check_out) = %s AND status IN ('approved', 'confirmed')",
            $target_date
        ) );

        foreach ( $bookings as $booking ) {
            // Only send if first review email was already sent.
            if ( ! self::has_been_sent( $booking->id, 'post_checkout' ) ) continue;
            // Skip if follow-up was already sent.
            if ( self::has_been_sent( $booking->id, 'review_followup' ) ) continue;

            $homestay = get_post( $booking->homestay_id );
            $hs_title = $homestay ? $homestay->post_title : 'Homestay';
            $review_link = site_url( '/leave-review/?booking_id=' . $booking->id . '&token=' . wp_create_nonce( 'hhb_review_' . $booking->id ) );

            EmailNotifier::send_and_log( $booking->id, 'review_followup', $booking->customer_email,
                sprintf( __( 'Quick Reminder — Share Your Experience at %s', 'himalayan-homestay-bookings' ), $hs_title ),
                EmailNotifier::build_html([
                    'heading'  => __( 'We’d Love to Hear from You!', 'himalayan-homestay-bookings' ),
                    'message'  => sprintf(
                        __( 'Hi %s, we sent you a review request a few days ago for your stay at <strong>%s</strong>. It only takes a moment and makes a huge difference for small mountain homestays like ours.', 'himalayan-homestay-bookings' ),
                        esc_html( $booking->customer_name ),
                        esc_html( $hs_title )
                    ),
                    'cta_url'  => $review_link,
                    'cta_text' => __( 'Leave a Review', 'himalayan-homestay-bookings' ),
                    'footer'   => __( 'This is our last reminder. Thank you for your time!', 'himalayan-homestay-bookings' ),
                ])
            );
        }
    }

    /**
     * Win-back Primary (60 days after checkout — soft returning guest offer)
     */
    private static function send_win_back_primary(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_bookings';

        $opts        = get_option( \Himalayan\Homestay\Interface\Admin\SettingsPage::OPTION_KEY, [] );
        $primary_days = max( 1, intval( $opts['win_back_primary_days'] ?? 60 ) );
        $target_date = date( 'Y-m-d', strtotime( "-{$primary_days} days" ) );

        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE DATE(check_out) = %s AND status IN ('approved', 'confirmed')",
            $target_date
        ) );

        foreach ( $bookings as $booking ) {
            if ( self::has_been_sent( $booking->id, 'win_back_primary' ) ) continue;

            $homestay = get_post( $booking->homestay_id );
            $hs_title = $homestay ? $homestay->post_title : 'Homestay';

            $options = get_option( \Himalayan\Homestay\Interface\Admin\SettingsPage::OPTION_KEY, [] );

            $placeholders = ['{guest_name}', '{property_name}', '{check_in}', '{check_out}', '{total_price}', '{booking_id}'];
            $replacements = [
                $booking->customer_name, $hs_title,
                date_i18n( get_option( 'date_format' ), strtotime( $booking->check_in ) ),
                date_i18n( get_option( 'date_format' ), strtotime( $booking->check_out ) ),
                number_format( (float) $booking->total_price, 2 ), $booking->id,
            ];

            $default_subject = sprintf( __( 'Exclusive Returning Guest Offer for %s 🌟', 'himalayan-homestay-bookings' ), $booking->customer_name );
            $subject = ! empty( $options['email_subject_win_back_primary'] )
                ? str_replace( $placeholders, $replacements, $options['email_subject_win_back_primary'] )
                : $default_subject;

            $default_body = sprintf(
                __( 'Hi %s, it has been 2 months since your amazing stay at <strong>%s</strong>. We’d love to welcome you back! Use code <strong>RETURN10</strong> for 10%% off your next booking. The mountains are calling!', 'himalayan-homestay-bookings' ),
                esc_html( $booking->customer_name ),
                esc_html( $hs_title )
            );
            $body = ! empty( $options['email_body_win_back_primary'] )
                ? wp_kses_post( str_replace( $placeholders, $replacements, nl2br( $options['email_body_win_back_primary'] ) ) )
                : $default_body;

            EmailNotifier::send_and_log( $booking->id, 'win_back_primary', $booking->customer_email, $subject,
                EmailNotifier::build_html([
                    'heading'  => __( 'Come Back to the Himalayas 🏔️', 'himalayan-homestay-bookings' ),
                    'message'  => $body,
                    'cta_url'  => site_url( '/homestays/' ),
                    'cta_text' => __( 'Browse Homestays', 'himalayan-homestay-bookings' ),
                    'footer'   => __( 'Offer valid for 30 days. One use per guest.', 'himalayan-homestay-bookings' ),
                ])
            );
        }
    }

    /**
     * Win-back Secondary (180 days after checkout — seasonal/festival re-engagement)
     */
    private static function send_win_back_secondary(): void {
         global $wpdb;
        $table = $wpdb->prefix . 'himalayan_bookings';
        
        $opts          = get_option( \Himalayan\Homestay\Interface\Admin\SettingsPage::OPTION_KEY, [] );
        $secondary_days = max( 1, intval( $opts['win_back_secondary_days'] ?? 180 ) );
        $target_date   = date( 'Y-m-d', strtotime( "-{$secondary_days} days" ) );
        
        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE DATE(check_out) = %s AND status IN ('approved', 'confirmed')",
            $target_date
        ) );

        foreach ( $bookings as $booking ) {
            if ( self::has_been_sent( $booking->id, 'win_back' ) ) continue;

            $homestay = get_post( $booking->homestay_id );
            $hs_title = $homestay ? $homestay->post_title : 'Homestay';

            $options = get_option( \Himalayan\Homestay\Interface\Admin\SettingsPage::OPTION_KEY, [] );
            
            $placeholders = ['{guest_name}', '{property_name}', '{check_in}', '{check_out}', '{total_price}', '{booking_id}'];
            $replacements = [
                $booking->customer_name,
                $hs_title,
                date_i18n( get_option( 'date_format' ), strtotime( $booking->check_in ) ),
                date_i18n( get_option( 'date_format' ), strtotime( $booking->check_out ) ),
                number_format( (float) $booking->total_price, 2 ),
                $booking->id
            ];

            $default_subject = __( 'We Miss You! Here is a gift for your next trip 🎁', 'himalayan-homestay-bookings' );
            $subject = ! empty( $options['email_subject_win_back'] ) 
                ? str_replace( $placeholders, $replacements, $options['email_subject_win_back'] ) 
                : $default_subject;
                
            $default_body = sprintf(
                __( 'Hi %s, it has been 6 months since your stay at <strong>%s</strong>. To welcome you back to the Himalayas, use the promo code <strong>WELCOMEBACK10</strong> for 10%% off your next booking at any of our homestays!', 'himalayan-homestay-bookings' ),
                esc_html( $booking->customer_name ),
                esc_html( $hs_title )
            );
            $body = ! empty( $options['email_body_win_back'] ) 
                ? wp_kses_post( str_replace( $placeholders, $replacements, nl2br($options['email_body_win_back']) ) )
                : $default_body;

            EmailNotifier::send_and_log( $booking->id, 'win_back', $booking->customer_email, $subject,
                EmailNotifier::build_html([
                    'heading' => __( 'Time for another mountain retreat?', 'himalayan-homestay-bookings' ),
                    'message' => $body,
                    'cta_url'  => site_url('/homestays/'),
                    'cta_text' => __( 'Browse Homestays', 'himalayan-homestay-bookings' ),
                    'footer'  => __( 'We look forward to hosting you again.', 'himalayan-homestay-bookings' ),
                ])
            );
        }
    }

    /**
     * Checks if this exact email sequence type has already been fired for a specific booking.
     */
    private static function has_been_sent( $booking_id, $type ): bool {
        global $wpdb;
        $log_table = $wpdb->prefix . 'himalayan_email_log';
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(id) FROM {$log_table} WHERE booking_id = %d AND email_type = %s",
            $booking_id, $type
        ) );
        return intval($count) > 0;
    }
}
