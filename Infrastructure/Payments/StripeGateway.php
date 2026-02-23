<?php
namespace Himalayan\Homestay\Infrastructure\Payments;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class StripeGateway extends AbstractGateway {
    public function __construct() {
        $this->gateway_id = 'stripe';
        parent::__construct();
    }

    public function create_payment_link( $booking ) {
        // Logic to call Stripe Checkout Session API using $booking details
        // Returns a secure URL
        return 'https://checkout.stripe.com/pay/' . $booking['payment_token'];
    }

    public function handle_webhook() {
        // Parse incoming Stripe webhook
        // Call BookingManager::confirm_payment() if successful
    }

    public function get_settings_fields() {
        return array(
            'public_key'  => 'Stripe Public Key',
            'secret_key'  => 'Stripe Secret Key',
            'webhook_key' => 'Webhook Secret',
        );
    }
}
