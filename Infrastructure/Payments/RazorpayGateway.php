<?php
/**
 * Razorpay Payment Gateway
 *
 * Handles Razorpay API interactions for the Himalayan Homestay Bookings plugin.
 * Includes Order creation and webhook signature verification.
 *
 * @package Himalayan\Homestay\Infrastructure\Payments
 */

namespace Himalayan\Homestay\Infrastructure\Payments;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class RazorpayGateway extends AbstractGateway {

    public function __construct() {
        $this->gateway_id = 'razorpay';
        // Settings are stored globally under a single key in this plugin
        $this->settings = get_option( 'hhb_payment_settings', [] );
    }

    /**
     * Check if Razorpay is enabled and configured.
     */
    public function is_active(): bool {
        return ( isset( $this->settings['razorpay_enabled'] ) && 'yes' === $this->settings['razorpay_enabled'] )
            && ! empty( $this->settings['razorpay_key_id'] )
            && ! empty( $this->settings['razorpay_key_secret'] );
    }

    /**
     * Get the Razorpay Key ID for frontend checkout.
     */
    public function get_key_id(): string {
        return $this->settings['razorpay_key_id'] ?? '';
    }

    /**
     * Generate the payment link URL for the email.
     */
    public function create_payment_link( $booking ): string {
        return add_query_arg(
            [
                'hhb_pay' => $booking->id,
                'token'   => $booking->payment_token,
            ],
            home_url( '/' )
        );
    }

    /**
     * Create a Razorpay Order via Server-to-Server API.
     * Required before opening the checkout modal.
     *
     * @param object $booking The booking object.
     * @param int $amount_in_cents Amount in paise (e.g. 500.00 INR = 50000)
     * @return array Array containing the 'id' (order_id) or an error.
     */
    public function create_order( $booking, int $amount_in_cents ): array {
        if ( ! $this->is_active() ) {
            return [ 'error' => __( 'Razorpay is not configured.', 'himalayan-homestay-bookings' ) ];
        }

        $key_id     = $this->settings['razorpay_key_id'];
        $key_secret = $this->settings['razorpay_key_secret'];
        $url        = 'https://api.razorpay.com/v1/orders';
        $currency   = 'INR'; // Ensuring INR for Indian payment

        // Razorpay requires receipt to be string <= 40 chars
        $receipt = 'RCPT_HHB_' . $booking->id;

        $payload_data = [
            'amount'   => $amount_in_cents,
            'currency' => $currency,
            'receipt'  => $receipt,
            'notes'    => [
                'booking_id' => $booking->id,
                'customer'   => $booking->customer_name,
            ]
        ];

        if ( ! empty( $booking->payment_expires_at ) ) {
            // Convert to UNIX timestamp for Razorpay.
            // Assumes payment_expires_at is in UTC (gmdate), otherwise adjust for timezone.
            // Using standard strtotime since we saved it using 'gmdate' explicitly in BookingManager.
            $payload_data['expire_by'] = strtotime( $booking->payment_expires_at . ' UTC' );
        }

        $payload = wp_json_encode( $payload_data );

        $response = wp_remote_post( $url, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode( $key_id . ':' . $key_secret ),
            ],
            'body'    => $payload,
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'error' => $response->get_error_message() ];
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( empty( $data['id'] ) ) {
            return [ 'error' => $data['error']['description'] ?? __( 'Unknown API error.', 'himalayan-homestay-bookings' ) ];
        }

        return $data;
    }

    /**
     * Initiate a full or partial refund via the Razorpay Payments Refund API.
     *
     * @param string $payment_id  The Razorpay payment ID (pay_XXXXXX).
     * @param int    $amount_paise Refund amount in paise. Pass 0 for full refund.
     * @return array Razorpay refund object on success, or ['error' => '...'] on failure.
     */
    public function refund( string $payment_id, int $amount_paise = 0 ): array {
        if ( ! $this->is_active() ) {
            return [ 'error' => __( 'Razorpay is not configured.', 'himalayan-homestay-bookings' ) ];
        }

        if ( empty( $payment_id ) ) {
            return [ 'error' => __( 'Missing payment ID for refund.', 'himalayan-homestay-bookings' ) ];
        }

        $key_id     = $this->settings['razorpay_key_id'];
        $key_secret = $this->settings['razorpay_key_secret'];
        $url        = 'https://api.razorpay.com/v1/payments/' . $payment_id . '/refund';

        $payload_data = [];
        if ( $amount_paise > 0 ) {
            $payload_data['amount'] = $amount_paise; // Partial refund
        }
        // If amount is 0, Razorpay issues a full refund automatically.

        try {
            $response = wp_remote_post( $url, [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode( $key_id . ':' . $key_secret ),
                ],
                'body'    => wp_json_encode( $payload_data ),
                'timeout' => 30,
            ] );

            if ( is_wp_error( $response ) ) {
                $err = $response->get_error_message();
                error_log( 'HHB Refund API Error: ' . $err );
                return [ 'error' => $err ];
            }

            $http_code = wp_remote_retrieve_response_code( $response );
            $body      = wp_remote_retrieve_body( $response );
            $data      = json_decode( $body, true );

            if ( $http_code >= 400 || empty( $data['id'] ) ) {
                $error_desc = $data['error']['description'] ?? __( 'Refund API returned an unexpected response.', 'himalayan-homestay-bookings' );
                error_log( sprintf( 'HHB Refund Failed [HTTP %d]: %s — Payment: %s', $http_code, $error_desc, $payment_id ) );
                return [ 'error' => $error_desc ];
            }

            // Verify refund status from the response.
            if ( isset( $data['status'] ) && $data['status'] !== 'processed' ) {
                error_log( sprintf( 'HHB Refund Pending: Refund %s status is "%s" (not processed yet).', $data['id'], $data['status'] ) );
            }

            return $data; // Contains id, amount, status, payment_id, etc.

        } catch ( \Exception $e ) {
            error_log( 'HHB Refund Exception: ' . $e->getMessage() );
            return [ 'error' => $e->getMessage() ];
        }
    }

    /**
     * Verify Webhook Signature securely.
     */
    public function verify_signature( string $payload, string $signature ): bool {
        if ( empty( $this->settings['razorpay_webhook_secret'] ) ) {
            // If no webhook secret is set in settings, we cannot verify webhooks safely.
            // A production environment should define this.
            return false;
        }

        $expected_signature = hash_hmac( 'sha256', $payload, $this->settings['razorpay_webhook_secret'] );
        return hash_equals( $expected_signature, $signature );
    }

    /**
     * Handle incoming webhooks for payment confirmations.
     */
    public function handle_webhook(): void {
        // Implementation handled in RESTController.php for API routing.
    }

    public function get_settings_fields(): array {
        return []; // Handled separately in SettingsPage.php
    }
}
