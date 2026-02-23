<?php
/**
 * Email Notifier
 *
 * Sends professional HTML emails for booking lifecycle events and logs
 * them to the himalayan_email_log table.
 *
 * @package Himalayan\Homestay\Infrastructure\Notifications
 */

namespace Himalayan\Homestay\Infrastructure\Notifications;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EmailNotifier {

    public static function init(): void {
        // Hook into booking lifecycle events fired by BookingManager.
        add_action( 'himalayan_booking_created',   [ __CLASS__, 'on_booking_created' ] );
        add_action( 'himalayan_booking_approved',   [ __CLASS__, 'on_booking_approved' ] );
        add_action( 'himalayan_payment_confirmed',  [ __CLASS__, 'on_payment_confirmed' ] );
    }

    // =========================================================================
    // Event Handlers
    // =========================================================================

    public static function on_booking_created( int $booking_id ): void {
        $booking = self::get_booking( $booking_id );
        if ( ! $booking ) return;

        $homestay = get_post( $booking->homestay_id );
        $hs_title = $homestay ? $homestay->post_title : 'Homestay';

        // Email to customer.
        self::send_and_log( $booking_id, 'booking_received', $booking->customer_email,
            sprintf( __( 'Booking Request Received — %s', 'himalayan-homestay-bookings' ), $hs_title ),
            self::build_html([
                'heading'  => __( 'Thank You for Your Inquiry!', 'himalayan-homestay-bookings' ),
                'message'  => sprintf(
                    __( 'Hi %s, we have received your booking request for <strong>%s</strong> from <strong>%s</strong> to <strong>%s</strong>. Our host will review and get back to you shortly.', 'himalayan-homestay-bookings' ),
                    esc_html( $booking->customer_name ),
                    esc_html( $hs_title ),
                    date_i18n( get_option( 'date_format' ), strtotime( $booking->check_in ) ),
                    date_i18n( get_option( 'date_format' ), strtotime( $booking->check_out ) )
                ),
                'details'  => self::booking_details_table( $booking, $hs_title ),
                'footer'   => __( 'You will receive another email once the host approves your booking.', 'himalayan-homestay-bookings' ),
            ])
        );

        // Email to admin.
        self::send_and_log( $booking_id, 'admin_new_booking', get_option( 'admin_email' ),
            sprintf( __( '🔔 New Booking Request — %s', 'himalayan-homestay-bookings' ), $hs_title ),
            self::build_html([
                'heading'  => __( 'New Booking Request', 'himalayan-homestay-bookings' ),
                'message'  => sprintf(
                    __( 'A new booking request was submitted by <strong>%s</strong> (%s) for <strong>%s</strong>.', 'himalayan-homestay-bookings' ),
                    esc_html( $booking->customer_name ),
                    esc_html( $booking->customer_email ),
                    esc_html( $hs_title )
                ),
                'details'  => self::booking_details_table( $booking, $hs_title ),
                'cta_url'  => admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-bookings&action=view&booking=' . $booking_id ),
                'cta_text' => __( 'Review Booking →', 'himalayan-homestay-bookings' ),
            ])
        );
    }

    public static function on_booking_approved( int $booking_id ): void {
        $booking = self::get_booking( $booking_id );
        if ( ! $booking ) return;

        $homestay = get_post( $booking->homestay_id );
        $hs_title = $homestay ? $homestay->post_title : 'Homestay';

        self::send_and_log( $booking_id, 'booking_approved', $booking->customer_email,
            sprintf( __( '✅ Booking Approved — %s', 'himalayan-homestay-bookings' ), $hs_title ),
            self::build_html([
                'heading' => __( 'Your Booking Has Been Approved!', 'himalayan-homestay-bookings' ),
                'message' => sprintf(
                    __( 'Great news, %s! Your booking at <strong>%s</strong> has been approved by the host.', 'himalayan-homestay-bookings' ),
                    esc_html( $booking->customer_name ),
                    esc_html( $hs_title )
                ),
                'details' => self::booking_details_table( $booking, $hs_title ),
                'footer'  => __( 'Please proceed with payment to confirm your reservation. The host is looking forward to welcoming you!', 'himalayan-homestay-bookings' ),
            ])
        );
    }

    public static function on_payment_confirmed( int $booking_id ): void {
        $booking = self::get_booking( $booking_id );
        if ( ! $booking ) return;

        $homestay = get_post( $booking->homestay_id );
        $hs_title = $homestay ? $homestay->post_title : 'Homestay';

        // Confirmation to customer.
        self::send_and_log( $booking_id, 'payment_confirmed', $booking->customer_email,
            sprintf( __( '🎉 Booking Confirmed — %s', 'himalayan-homestay-bookings' ), $hs_title ),
            self::build_html([
                'heading' => __( 'Booking Confirmed — See You Soon!', 'himalayan-homestay-bookings' ),
                'message' => sprintf(
                    __( 'Hi %s, your payment has been received and your booking at <strong>%s</strong> is now confirmed!', 'himalayan-homestay-bookings' ),
                    esc_html( $booking->customer_name ),
                    esc_html( $hs_title )
                ),
                'details' => self::booking_details_table( $booking, $hs_title ),
                'footer'  => __( 'If you have any questions about your stay, feel free to reply to this email.', 'himalayan-homestay-bookings' ),
            ])
        );

        // Notify admin.
        self::send_and_log( $booking_id, 'admin_payment_confirmed', get_option( 'admin_email' ),
            sprintf( __( '💰 Payment Confirmed — Booking #%d', 'himalayan-homestay-bookings' ), $booking_id ),
            self::build_html([
                'heading' => __( 'Payment Received', 'himalayan-homestay-bookings' ),
                'message' => sprintf(
                    __( 'Payment of <strong>%s</strong> has been received for Booking #%d at %s.', 'himalayan-homestay-bookings' ),
                    esc_html( '$' . number_format( (float) $booking->total_price, 2 ) ),
                    $booking_id,
                    esc_html( $hs_title )
                ),
                'details' => self::booking_details_table( $booking, $hs_title ),
            ])
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private static function get_booking( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}himalayan_bookings WHERE id = %d", $id
        ) );
    }

    private static function booking_details_table( object $booking, string $hs_title ): string {
        $fmt    = get_option( 'date_format' );
        $nights = (int) ( ( strtotime( $booking->check_out ) - strtotime( $booking->check_in ) ) / DAY_IN_SECONDS );
        $rows   = [
            __( 'Property', 'himalayan-homestay-bookings' )   => esc_html( $hs_title ),
            __( 'Guest Name', 'himalayan-homestay-bookings' ) => esc_html( $booking->customer_name ),
            __( 'Email', 'himalayan-homestay-bookings' )      => esc_html( $booking->customer_email ),
            __( 'Phone', 'himalayan-homestay-bookings' )      => esc_html( $booking->customer_phone ),
            __( 'Check-In', 'himalayan-homestay-bookings' )   => date_i18n( $fmt, strtotime( $booking->check_in ) ),
            __( 'Check-Out', 'himalayan-homestay-bookings' )  => date_i18n( $fmt, strtotime( $booking->check_out ) ),
            __( 'Nights', 'himalayan-homestay-bookings' )     => $nights,
            __( 'Total', 'himalayan-homestay-bookings' )      => '<strong>$' . number_format( (float) $booking->total_price, 2 ) . '</strong>',
        ];

        $html = '<table style="width:100%;border-collapse:collapse;margin:20px 0">';
        foreach ( $rows as $label => $value ) {
            $html .= sprintf(
                '<tr><td style="padding:8px 12px;border-bottom:1px solid #eee;color:#666;width:40%%">%s</td>'
                . '<td style="padding:8px 12px;border-bottom:1px solid #eee;font-weight:500">%s</td></tr>',
                $label, $value
            );
        }
        $html .= '</table>';
        return $html;
    }

    private static function build_html( array $args ): string {
        $heading  = $args['heading']  ?? '';
        $message  = $args['message']  ?? '';
        $details  = $args['details']  ?? '';
        $footer   = $args['footer']   ?? '';
        $cta_url  = $args['cta_url']  ?? '';
        $cta_text = $args['cta_text'] ?? '';

        $site_name = get_bloginfo( 'name' );

        $cta_html = '';
        if ( $cta_url ) {
            $cta_html = sprintf(
                '<p style="text-align:center;margin:24px 0"><a href="%s" style="display:inline-block;padding:12px 28px;background:#f45c25;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;font-size:14px">%s</a></p>',
                esc_url( $cta_url ), esc_html( $cta_text )
            );
        }

        return sprintf(
            '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif">'
            . '<div style="max-width:600px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)">'
            // Header bar
            . '<div style="background:linear-gradient(135deg,#f45c25,#e04010);padding:28px 32px;text-align:center">'
            . '<h1 style="margin:0;color:#fff;font-size:20px;font-weight:700">%s</h1>'
            . '</div>'
            // Body
            . '<div style="padding:32px">'
            . '<p style="font-size:15px;line-height:1.6;color:#333">%s</p>'
            . '%s'  // details table
            . '%s'  // CTA button
            . '%s'  // footer text
            . '</div>'
            // Footer
            . '<div style="padding:16px 32px;background:#f9f9f9;text-align:center;font-size:12px;color:#999;border-top:1px solid #eee">'
            . '© %s %s. All rights reserved.'
            . '</div>'
            . '</div></body></html>',
            esc_html( $heading ),
            $message,
            $details,
            $cta_html,
            $footer ? '<p style="font-size:13px;color:#888;margin-top:20px;border-top:1px solid #eee;padding-top:16px">' . esc_html( $footer ) . '</p>' : '',
            date( 'Y' ),
            esc_html( $site_name )
        );
    }

    private static function send_and_log( int $booking_id, string $type, string $to, string $subject, string $html_body ): bool {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
        ];

        $sent = wp_mail( $to, $subject, $html_body, $headers );

        // Log to database.
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'himalayan_email_log', [
            'booking_id' => $booking_id,
            'email_type' => $type,
            'recipient'  => $to,
            'subject'    => $subject,
            'status'     => $sent ? 'sent' : 'failed',
        ]);

        return $sent;
    }
}
