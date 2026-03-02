<?php
namespace Himalayan\Homestay\Frontend;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class HostApplication {

    public static function init() {
        add_action( 'wp_ajax_hhb_host_application', array( __CLASS__, 'handle_submission' ) );
        add_action( 'wp_ajax_nopriv_hhb_host_application', array( __CLASS__, 'handle_submission' ) );
    }

    public static function handle_submission() {
        // Verify nonce
        if ( ! isset( $_POST['hm_host_nonce'] ) || ! wp_verify_nonce( $_POST['hm_host_nonce'], 'hm_host_application' ) ) {
            wp_send_json_error( __( 'Security check failed.', 'himalayan-homestay-bookings' ) );
        }

        // Honeypot Trap
        if ( ! empty( $_POST['hhb_host_website'] ) ) {
            // Silently fail for bots
            wp_send_json_success( __( 'Application submitted successfully!', 'himalayan-homestay-bookings' ) );
        }

        // Rate limiting: max 3 applications per IP per hour.
        $ip       = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
        $rate_key = 'hhb_host_app_rate_' . md5( $ip );
        $attempts = (int) get_transient( $rate_key );
        if ( $attempts >= 3 ) {
            wp_send_json_error( __( 'Too many submissions. Please try again in an hour.', 'himalayan-homestay-bookings' ) );
        }
        set_transient( $rate_key, $attempts + 1, HOUR_IN_SECONDS );

        // Sanitize and validate inputs
        $name     = sanitize_text_field( $_POST['host_name'] ?? '' );
        $email    = sanitize_email( $_POST['host_email'] ?? '' );
        $phone    = sanitize_text_field( $_POST['host_phone'] ?? '' );

        if ( empty( $name ) || empty( $email ) || empty( $phone ) ) {
            wp_send_json_error( __( 'Please fill all required fields.', 'himalayan-homestay-bookings' ) );
        }

        // Create the application post
        $post_title = sprintf( 'Application - %s', $name );
        $post_id = wp_insert_post( array(
            'post_title'  => $post_title,
            'post_type'   => 'hhb_host_app',
            'post_status' => 'pending',
            'meta_input'  => array(
                'host_name'     => $name,
                'host_email'    => $email,
                'host_phone'    => $phone,
            ),
        ) );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( __( 'Could not save application. Please try again.', 'himalayan-homestay-bookings' ) );
        }

        // Send email notification to admin
        $admin_email = get_option( 'admin_email' );
        $subject = sprintf( __( 'New Host Application: %s', 'himalayan-homestay-bookings' ), $name );
        $body = sprintf(
            "Name: %s\nEmail: %s\nPhone: %s\n\nView in admin: %s",
            $name, $email, $phone,
            admin_url( 'edit.php?post_type=hhb_host_app' )
        );
        wp_mail( $admin_email, $subject, $body );

        wp_send_json_success( __( 'Application submitted successfully!', 'himalayan-homestay-bookings' ) );
    }
}
