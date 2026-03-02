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

require_once dirname( __DIR__ ) . '/PDF/InvoiceGenerator.php';

class EmailNotifier {

    public static function init(): void {
        // Hook into booking lifecycle events fired by BookingManager.
        add_action( 'himalayan_booking_created',   [ __CLASS__, 'on_booking_created' ] );
        add_action( 'himalayan_booking_approved',   [ __CLASS__, 'on_booking_approved' ] );
        add_action( 'himalayan_payment_confirmed',  [ __CLASS__, 'on_payment_confirmed' ] );
        add_action( 'himalayan_booking_dropped',    [ __CLASS__, 'on_booking_dropped' ] );
        add_action( 'himalayan_booking_cancelled',  [ __CLASS__, 'on_booking_cancelled' ] );
        add_action( 'himalayan_booking_refunded',   [ __CLASS__, 'on_booking_cancelled' ] );
    }

    // =========================================================================
    // Event Handlers
    // =========================================================================

    public static function on_booking_created( int $booking_id ): void {
        $booking = self::get_booking( $booking_id );
        if ( ! $booking ) return;

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
            $booking_id
        ];

        $default_subject_customer = sprintf( __( 'Booking Request Received — %s', 'himalayan-homestay-bookings' ), $hs_title );
        $subject_customer = ! empty( $options['email_subject_booking_received'] ) 
            ? str_replace( $placeholders, $replacements, $options['email_subject_booking_received'] ) 
            : $default_subject_customer;
            
        // Determine Email Type based on Payment Mode (Gateway)
        $is_cash = ($booking->gateway === 'cash');

        if ( $is_cash ) {
            $default_body_customer = sprintf(
                __( 'Dear %s,<br><br>Thank you for choosing <strong>%s</strong>! Your booking request from <strong>%s</strong> to <strong>%s</strong> has been successfully received and your dates have been securely reserved.<br><br><strong style="color:#e65100;">You have selected to pay on arrival.</strong> The host will review your request and send a final confirmation email shortly.<br><br>We look forward to welcoming you!', 'himalayan-homestay-bookings' ),
                esc_html( $booking->customer_name ),
                esc_html( $hs_title ),
                date_i18n( get_option( 'date_format' ), strtotime( $booking->check_in ) ),
                date_i18n( get_option( 'date_format' ), strtotime( $booking->check_out ) )
            );
            $cta_url  = '';
            $cta_text = '';
            $footer   = '';
            $heading  = __( 'Booking Reserved', 'himalayan-homestay-bookings' );
        } else {
            // Original Gateway Email
            $default_body_customer = sprintf(
                __( 'Dear %s,<br><br>Thank you for choosing <strong>%s</strong>! Your preferred dates from <strong>%s</strong> to <strong>%s</strong> are currently being held for you.<br><br><strong style="color:#e65100;">To secure your reservation, please complete your payment within the next 30 minutes.</strong> Please note that unpaid bookings are automatically released to the public after this window.', 'himalayan-homestay-bookings' ),
                esc_html( $booking->customer_name ),
                esc_html( $hs_title ),
                date_i18n( get_option( 'date_format' ), strtotime( $booking->check_in ) ),
                date_i18n( get_option( 'date_format' ), strtotime( $booking->check_out ) )
            );
            $cta_url  = esc_url( home_url( '/?hhb_confirmation=' . $booking_id ) );
            $cta_text = __( 'Complete Payment →', 'himalayan-homestay-bookings' );
            $footer   = __( 'If the dates are no longer available when you return, your booking will be dropped.', 'himalayan-homestay-bookings' );
            $heading  = __( 'Complete Your Payment', 'himalayan-homestay-bookings' );
        }
        $body_customer = ! empty( $options['email_body_booking_received'] ) 
            ? wp_kses_post( str_replace( $placeholders, $replacements, nl2br($options['email_body_booking_received']) ) )
            : $default_body_customer;
            
        self::send_and_log( $booking_id, 'booking_received', $booking->customer_email, $subject_customer,
            self::build_html([
                'heading'  => $heading,
                'message'  => $body_customer,
                'details'  => self::booking_details_table( $booking, $hs_title ),
                'cta_url'  => $cta_url,
                'cta_text' => $cta_text,
                'footer'   => $footer,
            ])
        );

        $default_subject_admin = sprintf( __( '🔔 New Booking Request — %s', 'himalayan-homestay-bookings' ), $hs_title );
        $subject_admin = ! empty( $options['email_subject_admin_new_booking'] ) 
            ? str_replace( $placeholders, $replacements, $options['email_subject_admin_new_booking'] ) 
            : $default_subject_admin;
            
        $default_body_admin = sprintf(
            __( 'A new booking request was submitted by <strong>%s</strong> (%s) for <strong>%s</strong>.', 'himalayan-homestay-bookings' ),
            esc_html( $booking->customer_name ),
            esc_html( $booking->customer_email ),
            esc_html( $hs_title )
        );
        $body_admin = ! empty( $options['email_body_admin_new_booking'] ) 
            ? wp_kses_post( str_replace( $placeholders, $replacements, nl2br($options['email_body_admin_new_booking']) ) )
            : $default_body_admin;

        // Email to admin — use SMTP-configured email as recipient, fallback to WP admin email.
        $admin_email = get_option( 'hhb_smtp_email' ) ?: get_option( 'admin_email' );
        self::send_and_log( $booking_id, 'admin_new_booking', $admin_email, $subject_admin,
            self::build_html([
                'heading'  => __( 'New Booking Request', 'himalayan-homestay-bookings' ),
                'message'  => $body_admin,
                'details'  => self::booking_details_table( $booking, $hs_title ),
                'cta_url'  => admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-bookings&action=view&booking=' . $booking_id ),
                'cta_text' => __( 'Review Booking →', 'himalayan-homestay-bookings' ),
            ])
        );
    }

    public static function on_booking_dropped( int $booking_id ): void {
        $booking = self::get_booking( $booking_id );
        if ( ! $booking ) return;

        $homestay = get_post( $booking->homestay_id );
        $hs_title = $homestay ? $homestay->post_title : 'Homestay';
        
        $placeholders = ['{guest_name}', '{property_name}'];
        $replacements = [$booking->customer_name, $hs_title];
        
        $subject_customer = sprintf( __( 'You left your booking incomplete — %s', 'himalayan-homestay-bookings' ), $hs_title );
        $body_customer    = sprintf(
            __( 'Dear %s,<br><br>We saw that you were just finalizing your booking for <strong>%s</strong> but didn\'t complete the payment. You\'re so close!<br><br>Your dates have been temporarily released back to the calendar. If you still want to secure your getaway, simply click the button below to jump right back in and finish your reservation before someone else grabs those dates.<br><br>If you ran into any issues or have questions, just reply to this email. We would love to host you!', 'himalayan-homestay-bookings' ),
            esc_html( $booking->customer_name ),
            esc_html( $hs_title )
        );

        $cta_url  = esc_url( home_url( '/?hhb_confirmation=' . $booking_id ) );
        $cta_text = __( 'Complete Payment →', 'himalayan-homestay-bookings' );

        self::send_and_log( $booking_id, 'booking_dropped', $booking->customer_email, $subject_customer,
            self::build_html([
                'heading'  => __( 'Complete Your Booking', 'himalayan-homestay-bookings' ),
                'message'  => wp_kses_post( $body_customer ),
                'details'  => self::booking_details_table( $booking, $hs_title ),
                'cta_url'  => $cta_url,
                'cta_text' => $cta_text,
                'footer'   => __( 'Note: If the dates have been booked by someone else while you were away, you will not be able to complete the payment.', 'himalayan-homestay-bookings' ),
            ])
        );
    }

    public static function on_booking_approved( int $booking_id ): void {
        $booking = self::get_booking( $booking_id );
        if ( ! $booking ) return;

        $homestay = get_post( $booking->homestay_id );
        $hs_title = $homestay ? $homestay->post_title : 'Homestay';

        $cta_url  = '';
        $cta_text = '';
        
        $gateway = new \Himalayan\Homestay\Infrastructure\Payments\RazorpayGateway();
        if ( $gateway->is_active() ) {
            $cta_url  = $gateway->create_payment_link( $booking );
            $cta_text = __( 'Pay Now Securely →', 'himalayan-homestay-bookings' );
        }

        $options = get_option( \Himalayan\Homestay\Interface\Admin\SettingsPage::OPTION_KEY, [] );
        
        $placeholders = ['{guest_name}', '{property_name}', '{check_in}', '{check_out}', '{total_price}', '{booking_id}'];
        $replacements = [
            $booking->customer_name,
            $hs_title,
            date_i18n( get_option( 'date_format' ), strtotime( $booking->check_in ) ),
            date_i18n( get_option( 'date_format' ), strtotime( $booking->check_out ) ),
            number_format( (float) $booking->total_price, 2 ),
            $booking_id
        ];

        $default_subject_approved = sprintf( __( '✅ Booking Approved — %s', 'himalayan-homestay-bookings' ), $hs_title );
        $subject_approved = ! empty( $options['email_subject_booking_approved'] ) 
            ? str_replace( $placeholders, $replacements, $options['email_subject_booking_approved'] ) 
            : $default_subject_approved;
            
        $default_body_approved = sprintf(
            __( 'Great news, %s!<br><br>The host at <strong>%s</strong> has reviewed your request and happily approved your stay.<br><br><strong style="color:#e65100;">Your payment link is active for the next 60 minutes.</strong> Please click the button below to complete your payment and secure your dates before they are released back to the public.', 'himalayan-homestay-bookings' ),
            esc_html( $booking->customer_name ),
            esc_html( $hs_title )
        );
        $body_approved = ! empty( $options['email_body_booking_approved'] ) 
            ? wp_kses_post( str_replace( $placeholders, $replacements, nl2br($options['email_body_booking_approved']) ) )
            : $default_body_approved;

        self::send_and_log( $booking_id, 'booking_approved', $booking->customer_email, $subject_approved,
            self::build_html([
                'heading'  => __( 'Your Booking Has Been Approved!', 'himalayan-homestay-bookings' ),
                'message'  => $body_approved,
                'details'  => self::booking_details_table( $booking, $hs_title ),
                'cta_url'  => $cta_url,
                'cta_text' => $cta_text,
                'footer'   => __( 'Please proceed with payment to confirm your reservation.', 'himalayan-homestay-bookings' ),
            ])
        );
    }

    public static function on_payment_confirmed( int $booking_id ): void {
        $booking = self::get_booking( $booking_id );
        if ( ! $booking ) return;

        $homestay = get_post( $booking->homestay_id );
        $hs_title = $homestay ? $homestay->post_title : 'Homestay';

        // Assign atomic invoice number (idempotent — skips if already set).
        \Himalayan\Homestay\Infrastructure\PDF\InvoiceGenerator::assign_invoice_number( $booking_id );

        // Generate Invoice
        $attachments = [];
        $pdf = new \Himalayan\Homestay\Infrastructure\PDF\InvoiceGenerator( $booking_id );
        $pdf_path = $pdf->generate( 'F' );
        if ( $pdf_path && file_exists( $pdf_path ) ) {
            $attachments[] = $pdf_path;
        }

        $options = get_option( \Himalayan\Homestay\Interface\Admin\SettingsPage::OPTION_KEY, [] );
        
        $placeholders = ['{guest_name}', '{property_name}', '{check_in}', '{check_out}', '{total_price}', '{booking_id}'];
        $replacements = [
            $booking->customer_name,
            $hs_title,
            date_i18n( get_option( 'date_format' ), strtotime( $booking->check_in ) ),
            date_i18n( get_option( 'date_format' ), strtotime( $booking->check_out ) ),
            number_format( (float) $booking->total_price, 2 ),
            $booking_id
        ];

        $default_subject_confirmed = sprintf( __( '🎉 Booking Confirmed — %s', 'himalayan-homestay-bookings' ), $hs_title );
        $subject_confirmed = ! empty( $options['email_subject_payment_confirmed'] ) 
            ? str_replace( $placeholders, $replacements, $options['email_subject_payment_confirmed'] ) 
            : $default_subject_confirmed;
            
        $default_body_confirmed = sprintf(
            __( 'Dear %s,<br><br>Wonderful news! Your payment has been successfully processed and your reservation at <strong>%s</strong> is now officially confirmed.<br><br>Everything is set for your stay. You will find your official receipt and paid invoice attached to this email.', 'himalayan-homestay-bookings' ),
            esc_html( $booking->customer_name ),
            esc_html( $hs_title )
        );
        $body_confirmed = ! empty( $options['email_body_payment_confirmed'] ) 
            ? wp_kses_post( str_replace( $placeholders, $replacements, nl2br($options['email_body_payment_confirmed']) ) )
            : $default_body_confirmed;

        // Confirmation to customer.
        self::send_and_log( $booking_id, 'payment_confirmed', $booking->customer_email, $subject_confirmed,
            self::build_html([
                'heading' => __( 'Booking Confirmed — See You Soon!', 'himalayan-homestay-bookings' ),
                'message' => $body_confirmed,
                'details' => self::booking_details_table( $booking, $hs_title ),
                'footer'  => __( 'Your final invoice is attached. If you have any questions about your stay, feel free to reply to this email.', 'himalayan-homestay-bookings' ),
            ]),
            $attachments
        );

        // Notify admin — use SMTP-configured email as recipient, fallback to WP admin email.
        $admin_email = get_option( 'hhb_smtp_email' ) ?: get_option( 'admin_email' );
        self::send_and_log( $booking_id, 'admin_payment_confirmed', $admin_email,
            sprintf( __( '💰 Payment Confirmed — Booking #%d', 'himalayan-homestay-bookings' ), $booking_id ),
            self::build_html([
                'heading' => __( 'Payment Received', 'himalayan-homestay-bookings' ),
                'message' => sprintf(
                    __( 'Payment of <strong>%s</strong> has been received for Booking #%d at %s.', 'himalayan-homestay-bookings' ),
                    esc_html( '₹' . number_format( (float) $booking->total_price, 2 ) ),
                    $booking_id,
                    esc_html( $hs_title )
                ),
                'details' => self::booking_details_table( $booking, $hs_title ),
            ])
        );
    }

    /**
     * Fired when a booking's payment window expires (60 min after approval).
     * Sends a "Payment Link Expired" notification to the guest.
     */
    public static function on_payment_expired( int $booking_id ): void {
        $booking = self::get_booking( $booking_id );
        if ( ! $booking ) return;

        $homestay = get_post( $booking->homestay_id );
        $hs_title = $homestay ? $homestay->post_title : 'Homestay';

        $contact_url = site_url( '/contact/' );

        self::send_and_log( $booking_id, 'payment_expired', $booking->customer_email,
            sprintf( __( '⚠️ Payment Link Expired — Booking #%d', 'himalayan-homestay-bookings' ), $booking_id ),
            self::build_html([
                'heading' => __( 'Your Payment Link Has Expired', 'himalayan-homestay-bookings' ),
                'message' => sprintf(
                    __( 'Dear %s,<br><br>We regret to inform you that the 60-minute payment window for your reservation at <strong>%s</strong> (Check-in: %s) has securely closed, and the dates have been automatically released.<br><br>If you are still interested in staying with us, please contact us directly and we would be absolutely delighted to send you a renewed payment link.', 'himalayan-homestay-bookings' ),
                    esc_html( $booking->customer_name ),
                    esc_html( $hs_title ),
                    esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking->check_in ) ) )
                ),
                'details'  => self::booking_details_table( $booking, $hs_title ),
                'cta_url'  => $contact_url,
                'cta_text' => __( 'Contact Us to Rebook', 'himalayan-homestay-bookings' ),
                'footer'   => __( 'We hope to hear from you soon. Dates are available on a first-come, first-served basis.', 'himalayan-homestay-bookings' ),
            ])
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public static function get_booking( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}himalayan_bookings WHERE id = %d", $id
        ) );
    }

    public static function booking_details_table( object $booking, string $hs_title ): string {
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
            __( 'Total', 'himalayan-homestay-bookings' )      => '<strong>₹' . number_format( (float) $booking->total_price, 2 ) . '</strong>',
            __( 'Status', 'himalayan-homestay-bookings' )     => '<strong style="color:#e85e30;text-transform:capitalize">' . esc_html( ucfirst( $booking->status ) ) . '</strong>',
        ];

        // Only show transaction ID if it exists (i.e. payment was made)
        if ( ! empty( $booking->transaction_id ) ) {
            $rows[ __( 'Transaction ID', 'himalayan-homestay-bookings' ) ] = '<code style="font-size:12px;background:#f5f5f5;padding:2px 6px;border-radius:4px;">' . esc_html( $booking->transaction_id ) . '</code>';
        }

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

    public static function build_html( array $args ): string {
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
                '<p style="text-align:center;margin:24px 0"><a href="%s" style="display:inline-block;padding:14px 32px;background-color:#e85e30 !important;color:#ffffff !important;text-decoration:none;border-radius:30px;font-weight:bold;font-size:16px">%s</a></p>',
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

    public static function send_and_log( int $booking_id, string $type, string $to, string $subject, string $html_body, array $attachments = [] ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_email_log';

        // ── Idempotency Gate ─────────────────────────────────────────────
        // The UNIQUE KEY (booking_id, email_type) prevents duplicate sends.
        // Check application-level first to avoid unnecessary wp_mail() calls.
        $already_sent = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE booking_id = %d AND email_type = %s AND status = 'sent'",
            $booking_id, $type
        ) );

        if ( $already_sent ) {
            error_log( sprintf( 'HHB Email Idempotency: Skipped duplicate [%s] for Booking #%d.', $type, $booking_id ) );
            return true; // Already sent, no-op.
        }

        // ── Send ─────────────────────────────────────────────────────────
        $from_email = get_option( 'hhb_smtp_email' ) ?: get_option( 'admin_email' );
        $from_name  = get_option( 'hhb_smtp_from_name' ) ?: get_bloginfo( 'name' );
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];

        try {
            $sent = wp_mail( $to, $subject, $html_body, $headers, $attachments );
        } catch ( \Exception $e ) {
            error_log( sprintf( 'HHB Email Exception [%s] Booking #%d: %s', $type, $booking_id, $e->getMessage() ) );
            $sent = false;
        }

        // ── Log ONLY after successful send ───────────────────────────────
        // If wp_mail() failed, we do NOT insert a log row, allowing future retries.
        if ( $sent ) {
            $wpdb->insert( $table, [
                'booking_id' => $booking_id,
                'email_type' => $type,
                'recipient'  => $to,
                'subject'    => $subject,
                'status'     => 'sent',
            ] );
        } else {
            error_log( sprintf( 'HHB Email Failed [%s] for Booking #%d to %s. Will retry on next trigger.', $type, $booking_id, $to ) );
        }

        return $sent;
    }

    // =========================================================================
    // Cancellation & Refund Email
    // =========================================================================

    /**
     * Fired when admin cancels a confirmed booking and refund is processed.
     */
    public static function on_booking_cancelled( int $booking_id ): void {
        $booking = self::get_booking( $booking_id );
        if ( ! $booking ) return;

        $homestay = get_post( $booking->homestay_id );
        $hs_title = $homestay ? $homestay->post_title : 'Homestay';

        $refund_amount = ! empty( $booking->refund_amount ) ? '₹' . number_format( (float) $booking->refund_amount / 100, 2 ) : '';

        $body = sprintf(
            __( 'Dear %s,<br><br>We are writing to let you know that your reservation at <strong>%s</strong> (Check-in: %s) has been cancelled by the host.<br><br>%sWe sincerely apologize for any inconvenience. If you have any questions or would like to rebook alternative dates, please do not hesitate to contact us.<br><br>We hope to welcome you in the future!', 'himalayan-homestay-bookings' ),
            esc_html( $booking->customer_name ),
            esc_html( $hs_title ),
            esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking->check_in ) ) ),
            $refund_amount ? sprintf( __( '<strong>A full refund of %s has been initiated to your original payment method.</strong> Please allow 5–7 business days for the amount to reflect in your account.<br><br>', 'himalayan-homestay-bookings' ), $refund_amount ) : ''
        );

        self::send_and_log( $booking_id, 'booking_cancelled', $booking->customer_email,
            sprintf( __( '❌ Booking Cancelled — %s', 'himalayan-homestay-bookings' ), $hs_title ),
            self::build_html([
                'heading' => __( 'Your Booking Has Been Cancelled', 'himalayan-homestay-bookings' ),
                'message' => $body,
                'details' => self::booking_details_table( $booking, $hs_title ),
                'cta_url' => esc_url( site_url( '/contact/' ) ),
                'cta_text' => __( 'Contact Us', 'himalayan-homestay-bookings' ),
                'footer'  => __( 'If you believe this cancellation was made in error, please contact us immediately.', 'himalayan-homestay-bookings' ),
            ])
        );

        // Notify admin
        $admin_email = get_option( 'hhb_smtp_email' ) ?: get_option( 'admin_email' );
        self::send_and_log( $booking_id, 'admin_booking_cancelled', $admin_email,
            sprintf( __( '❌ Booking #%d Cancelled & Refunded', 'himalayan-homestay-bookings' ), $booking_id ),
            self::build_html([
                'heading' => __( 'Booking Cancelled', 'himalayan-homestay-bookings' ),
                'message' => sprintf(
                    __( 'Booking #%d at <strong>%s</strong> for guest <strong>%s</strong> has been cancelled and refunded.', 'himalayan-homestay-bookings' ),
                    $booking_id,
                    esc_html( $hs_title ),
                    esc_html( $booking->customer_name )
                ),
                'details' => self::booking_details_table( $booking, $hs_title ),
            ])
        );
    }
}
