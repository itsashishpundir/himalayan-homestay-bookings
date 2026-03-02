<?php
/**
 * Test Payment Gateway
 *
 * Simulates instant payment success for testing the booking flow.
 * Only active when test mode is enabled in the settings.
 *
 * @package Himalayan\Homestay\Infrastructure\Payments
 */

namespace Himalayan\Homestay\Infrastructure\Payments;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class TestGateway extends AbstractGateway {

    public function __construct() {
        $this->gateway_id = 'test';
        $this->settings = get_option( 'hhb_payment_settings', [] );
    }

    /**
     * Check if Test Gateway is enabled.
     */
    public function is_active(): bool {
        return ( isset( $this->settings['test_gateway_enabled'] ) && 'yes' === $this->settings['test_gateway_enabled'] );
    }

    /**
     * Generate the payment link URL for the email (if needed).
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
     * Handle incoming webhooks (not used for test gateway).
     */
    public function handle_webhook(): void {
    }

    /**
     * Settings fields (handled in SettingsPage.php).
     */
    public function get_settings_fields(): array {
        return [];
    }
}
