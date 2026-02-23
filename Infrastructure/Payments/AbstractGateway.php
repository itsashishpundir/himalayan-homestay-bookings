<?php
namespace Himalayan\Homestay\Infrastructure\Payments;

if ( ! defined( 'ABSPATH' ) ) { exit; }

abstract class AbstractGateway {
    protected $gateway_id;
    protected $settings;

    public function __construct() {
        $this->settings = get_option( 'hhb_gateway_' . $this->gateway_id, array() );
    }

    /**
     * Create a secure payment link or token for a booking.
     */
    abstract public function create_payment_link( $booking );

    /**
     * Handle incoming webhooks for payment confirmations.
     */
    abstract public function handle_webhook();

    /**
     * Get settings fields for admin UI.
     */
    abstract public function get_settings_fields();
}
