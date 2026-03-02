<?php
/**
 * Personal Data Erasure Hook
 *
 * Hooks into the native WordPress GDPR tools to anonymize booking records
 * when a user's data deletion request is approved by the admin.
 *
 * @package Himalayan\Homestay\Infrastructure\Admin
 */

namespace Himalayan\Homestay\Infrastructure\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PersonalDataEraser {

    public static function init(): void {
        add_filter( 'wp_privacy_personal_data_erasers', [ __CLASS__, 'register_eraser' ] );
    }

    /**
     * Registers our custom eraser callback with WP core.
     */
    public static function register_eraser( array $erasers ): array {
        $erasers['himalayan-homestay-bookings'] = [
            'eraser_friendly_name' => __( 'Homestay Bookings', 'himalayan-homestay-bookings' ),
            'callback'             => [ __CLASS__, 'erase_booking_data' ],
        ];
        return $erasers;
    }

    /**
     * Anonymizes customer data in the hhb_bookings table.
     * We do not delete the row entirely to preserve financial/occupancy reporting.
     *
     * @param string $email_address The email being erased.
     * @param int    $page          Pagination.
     * @return array Result of the erasure process.
     */
    public static function erase_booking_data( string $email_address, int $page = 1 ): array {
        global $wpdb;

        if ( empty( $email_address ) ) {
            return [
                'items_removed'  => false,
                'items_retained' => false,
                'messages'       => [],
                'done'           => true,
            ];
        }

        $table_name = $wpdb->prefix . 'hhb_bookings';

        // Find bookings associated with this email
        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE customer_email = %s",
            $email_address
        ) );

        $items_removed  = false;
        $items_retained = false;
        $messages       = [];

        if ( ! empty( $bookings ) ) {
            foreach ( $bookings as $booking ) {
                $anonymized = $wpdb->update(
                    $table_name,
                    [
                        'customer_name'  => __( 'Anonymized User', 'himalayan-homestay-bookings' ),
                        'customer_email' => $booking->id . '@deleted.local',
                        'customer_phone' => '0000000000',
                    ],
                    [ 'id' => $booking->id ],
                    [ '%s', '%s', '%s' ],
                    [ '%d' ]
                );

                if ( false !== $anonymized ) {
                    $items_removed = true;
                } else {
                    $items_retained = true;
                    $messages[]     = sprintf( __( 'Failed to anonymize booking #%d.', 'himalayan-homestay-bookings' ), $booking->id );
                }
            }
        }

        return [
            'items_removed'  => $items_removed,
            'items_retained' => $items_retained,
            'messages'       => $messages,
            'done'           => true,
        ];
    }
}
