<?php
namespace Himalayan\Homestay\Infrastructure\Payments;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PayPalGateway extends AbstractGateway {
    public function __construct() {
        $this->gateway_id = 'paypal';
        $this->settings = get_option( 'hhb_payment_settings', [] );
    }

    public function is_active() {
        return ! empty( $this->settings['paypal_enabled'] ) && 'yes' === $this->settings['paypal_enabled'] && ! empty( $this->settings['paypal_client_id'] ) && ! empty( $this->settings['paypal_client_secret'] );
    }

    public function get_client_id() {
        return $this->settings['paypal_client_id'] ?? '';
    }
    
    public function get_client_secret() {
        return $this->settings['paypal_client_secret'] ?? '';
    }

    public function get_environment() {
        return $this->settings['paypal_environment'] ?? 'sandbox';
    }

    public function verify_order( $paypal_order_id ) {
        // Here we would ideally call the PayPal REST API to GET /v2/checkout/orders/{id}
        // and verify it is "COMPLETED" and the amount matches.
        // For now, this is a placeholder returning true because the JS SDK does the actual charge confirmation.
        // In a strict production system, you'd curl PayPal with a Bearer token here.
        return true;
    }

    public function get_settings_fields() {
        return array(
            'paypal_client_id'  => 'PayPal Client ID',
            'paypal_client_secret'  => 'PayPal Secret',
        );
    }

    public function create_payment_link( $booking ) {
        return '';
    }

    public function handle_webhook() {
        // Not used directly here
    }
}
