<?php
/**
 * Newsletter Manager
 *
 * Handles subscribe / unsubscribe / campaign sending for the built-in newsletter system.
 *
 * @package Himalayan\Homestay\Infrastructure\Newsletter
 */

namespace Himalayan\Homestay\Infrastructure\Newsletter;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class NewsletterManager {

    public static function init() {
        // Subscribe via AJAX (both logged-in and guests)
        add_action( 'wp_ajax_hhb_newsletter_subscribe',        [ __CLASS__, 'ajax_subscribe' ] );
        add_action( 'wp_ajax_nopriv_hhb_newsletter_subscribe', [ __CLASS__, 'ajax_subscribe' ] );

        // Send campaign (admin only — called via admin page)
        add_action( 'wp_ajax_hhb_newsletter_send_campaign', [ __CLASS__, 'ajax_send_campaign' ] );

        // Unsubscribe via GET link
        add_action( 'init', [ __CLASS__, 'handle_unsubscribe' ] );
    }

    // -------------------------------------------------------------------------
    // Subscribe AJAX
    // -------------------------------------------------------------------------
    public static function ajax_subscribe() {
        check_ajax_referer( 'hhb_newsletter', 'nonce' );

        $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
        $name  = isset( $_POST['name'] )  ? sanitize_text_field( $_POST['name'] ) : '';

        if ( ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => 'Please enter a valid email address.' ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'hhb_newsletter_subscribers';

        // Check if already subscribed
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, status FROM {$table} WHERE email = %s",
            $email
        ) );

        if ( $existing ) {
            if ( $existing->status === 'active' ) {
                wp_send_json_success( [ 'message' => 'You are already subscribed!' ] );
            }
            // Re-subscribe if previously unsubscribed
            $wpdb->update( $table,
                [ 'status' => 'active', 'unsubscribed_at' => null, 'name' => $name ],
                [ 'id' => $existing->id ],
                [ '%s', null, '%s' ],
                [ '%d' ]
            );
            wp_send_json_success( [ 'message' => 'Welcome back! You have been re-subscribed.' ] );
        }

        $token = wp_generate_password( 32, false );
        $wpdb->insert( $table, [
            'email'             => $email,
            'name'              => $name,
            'status'            => 'active',
            'unsubscribe_token' => $token,
        ], [ '%s', '%s', '%s', '%s' ] );

        if ( $wpdb->last_error ) {
            wp_send_json_error( [ 'message' => 'Could not save your subscription. Please try again.' ] );
        }

        // Send welcome email to subscriber
        self::send_welcome_email( $email, $name, $token );

        wp_send_json_success( [ 'message' => 'You\'re subscribed! Thank you for joining us.' ] );
    }

    // -------------------------------------------------------------------------
    // Send Campaign AJAX (admin only)
    // -------------------------------------------------------------------------
    public static function ajax_send_campaign() {
        check_ajax_referer( 'hhb_newsletter_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }

        $subject = isset( $_POST['subject'] ) ? sanitize_text_field( $_POST['subject'] ) : '';
        $body    = isset( $_POST['body'] )    ? wp_kses_post( $_POST['body'] ) : '';

        if ( ! $subject || ! $body ) {
            wp_send_json_error( [ 'message' => 'Subject and body are required.' ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'hhb_newsletter_subscribers';

        $subscribers = $wpdb->get_results( "SELECT email, name, unsubscribe_token FROM {$table} WHERE status = 'active'" );

        if ( empty( $subscribers ) ) {
            wp_send_json_error( [ 'message' => 'No active subscribers found.' ] );
        }

        $sent  = 0;
        $failed = 0;
        $site_name = get_bloginfo( 'name' );
        $unsubscribe_base = add_query_arg( 'hhb_unsub', '', home_url( '/' ) );

        foreach ( $subscribers as $sub ) {
            $unsub_url = add_query_arg( [
                'hhb_unsub' => '1',
                'token'     => $sub->unsubscribe_token,
                'email'     => rawurlencode( $sub->email ),
            ], home_url( '/' ) );

            $html = self::build_campaign_email( $sub->name ?: $sub->email, $subject, $body, $unsub_url );

            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $site_name . ' <' . get_option( 'admin_email' ) . '>',
            ];

            $ok = wp_mail( $sub->email, $subject, $html, $headers );
            $ok ? $sent++ : $failed++;
        }

        wp_send_json_success( [
            'message' => "Campaign sent! {$sent} delivered" . ( $failed ? ", {$failed} failed." : '.' ),
            'sent'    => $sent,
            'failed'  => $failed,
        ] );
    }

    // -------------------------------------------------------------------------
    // Unsubscribe via GET link (?hhb_unsub=1&token=xxx&email=yyy)
    // -------------------------------------------------------------------------
    public static function handle_unsubscribe() {
        if ( empty( $_GET['hhb_unsub'] ) ) return;

        $token = isset( $_GET['token'] ) ? sanitize_text_field( $_GET['token'] ) : '';
        $email = isset( $_GET['email'] ) ? sanitize_email( rawurldecode( $_GET['email'] ) ) : '';

        if ( ! $token || ! $email ) return;

        global $wpdb;
        $table = $wpdb->prefix . 'hhb_newsletter_subscribers';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE email = %s AND unsubscribe_token = %s",
            $email, $token
        ) );

        if ( $row ) {
            $wpdb->update( $table,
                [ 'status' => 'unsubscribed', 'unsubscribed_at' => current_time( 'mysql' ) ],
                [ 'id' => $row->id ],
                [ '%s', '%s' ], [ '%d' ]
            );
        }

        // Show a simple notice and redirect home
        wp_redirect( add_query_arg( 'hhb_unsub_done', '1', home_url( '/' ) ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // Admin notification on new subscriber
    // -------------------------------------------------------------------------
    private static function notify_admin( string $email, string $name ) {
        $admin_email = get_option( 'admin_email' );
        $site_name   = get_bloginfo( 'name' );
        $count       = self::get_subscriber_count();
        $manage_url  = admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-newsletter' );
        $display     = $name ? "{$name} ({$email})" : $email;

        $subject = "[{$site_name}] New Newsletter Subscriber";

        $body = "
            <p>You have a new newsletter subscriber!</p>
            <p><strong>Email:</strong> {$email}<br>
            " . ( $name ? "<strong>Name:</strong> {$name}<br>" : '' ) . "
            <strong>Total active subscribers:</strong> {$count}</p>
            <p><a href=\"{$manage_url}\">View all subscribers →</a></p>
        ";

        $unsub_url = $manage_url; // admin link, not an unsubscribe
        $html = self::build_campaign_email( 'Admin', $subject, $body, $manage_url );

        wp_mail( $admin_email, $subject, $html, [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . $admin_email . '>',
        ] );
    }

    // -------------------------------------------------------------------------
    // Welcome email
    // -------------------------------------------------------------------------
    private static function send_welcome_email( string $email, string $name, string $token ) {
        $site_name  = get_bloginfo( 'name' );
        $unsub_url  = add_query_arg( [
            'hhb_unsub' => '1',
            'token'     => $token,
            'email'     => rawurlencode( $email ),
        ], home_url( '/' ) );

        $greeting = $name ? "Hello {$name}," : 'Hello,';
        $subject  = "Welcome to {$site_name}!";

        $body = "
            <p>{$greeting}</p>
            <p>Thank you for subscribing to our newsletter. You'll be the first to hear about new homestays, travel stories, and exclusive offers from the Himalayas.</p>
            <p>We look forward to inspiring your next adventure!</p>
            <p>Warm regards,<br>The {$site_name} Team</p>
        ";

        $html = self::build_campaign_email( $name ?: $email, $subject, $body, $unsub_url );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option( 'admin_email' ) . '>',
        ];

        wp_mail( $email, $subject, $html, $headers );
    }

    // -------------------------------------------------------------------------
    // HTML Email Template
    // -------------------------------------------------------------------------
    public static function build_campaign_email( string $recipient_name, string $subject, string $body, string $unsub_url ): string {
        $site_name = get_bloginfo( 'name' );
        $site_url  = home_url( '/' );
        $logo_url  = get_site_icon_url( 64 );
        $year      = date( 'Y' );

        $logo_html = $logo_url
            ? "<img src=\"{$logo_url}\" alt=\"{$site_name}\" style=\"height:48px;margin-bottom:8px;\">"
            : "<span style=\"font-size:28px;font-weight:800;color:#e85e30;\">{$site_name}</span>";

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$subject}</title>
</head>
<body style="margin:0;padding:0;background:#f4f1ee;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">

  <!-- Outer wrapper -->
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f1ee;padding:40px 16px;">
    <tr><td align="center">

      <!-- Card -->
      <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">

        <!-- ── Header ── -->
        <tr>
          <td style="background:linear-gradient(135deg,#1a1a2e 0%,#2d1a3e 100%);padding:40px 48px;text-align:center;">
            {$logo_html}
            <p style="margin:12px 0 0;color:#c8aff0;font-size:13px;letter-spacing:2px;text-transform:uppercase;">Newsletter</p>
          </td>
        </tr>

        <!-- ── Hero band ── -->
        <tr>
          <td style="background:#e85e30;padding:4px 0;"></td>
        </tr>

        <!-- ── Body ── -->
        <tr>
          <td style="padding:48px 48px 32px;">
            <p style="margin:0 0 24px;font-size:15px;color:#444;line-height:1.7;">{$body}</p>
          </td>
        </tr>

        <!-- ── CTA ── -->
        <tr>
          <td style="padding:0 48px 40px;text-align:center;">
            <a href="{$site_url}" style="display:inline-block;background:#e85e30;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:14px 36px;border-radius:8px;letter-spacing:.5px;">
              Explore Homestays →
            </a>
          </td>
        </tr>

        <!-- ── Divider ── -->
        <tr>
          <td style="padding:0 48px;">
            <hr style="border:none;border-top:1px solid #eee;margin:0;">
          </td>
        </tr>

        <!-- ── Footer ── -->
        <tr>
          <td style="padding:24px 48px;text-align:center;">
            <p style="margin:0 0 8px;font-size:12px;color:#999;">
              © {$year} {$site_name} · You're receiving this because you subscribed.
            </p>
            <p style="margin:0;font-size:12px;">
              <a href="{$unsub_url}" style="color:#e85e30;text-decoration:none;">Unsubscribe</a>
            </p>
          </td>
        </tr>

      </table>
      <!-- /Card -->

    </td></tr>
  </table>

</body>
</html>
HTML;
    }

    // -------------------------------------------------------------------------
    // Helpers used by admin page
    // -------------------------------------------------------------------------
    public static function get_subscriber_count(): int {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}hhb_newsletter_subscribers WHERE status = 'active'" );
    }

    public static function get_all_subscribers( int $per_page = 50, int $page = 1 ): array {
        global $wpdb;
        $table  = $wpdb->prefix . 'hhb_newsletter_subscribers';
        $offset = ( $page - 1 ) * $per_page;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY subscribed_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ) ) ?: [];
    }

    public static function get_total_subscribers(): int {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}hhb_newsletter_subscribers" );
    }

    public static function delete_subscriber( int $id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'hhb_newsletter_subscribers', [ 'id' => $id ], [ '%d' ] );
    }
}
