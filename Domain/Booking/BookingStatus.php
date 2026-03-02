<?php
/**
 * Booking Status Constants & Transition Map
 *
 * Defines all valid booking statuses as constants and the allowed
 * transitions between them. Every status change in the system MUST
 * go through BookingManager::transition_status() which consults this map.
 *
 * @package Himalayan\Homestay\Domain\Booking
 */

namespace Himalayan\Homestay\Domain\Booking;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class BookingStatus {

    // ── Status Constants ─────────────────────────────────────────────────
    const PENDING          = 'pending';
    const APPROVED         = 'approved';
    const CONFIRMED        = 'confirmed';
    const DROPPED          = 'dropped';
    const PAYMENT_EXPIRED  = 'payment_expired';
    const CANCELLED        = 'cancelled';
    const REFUNDED         = 'refunded';

    // ── Allowed Transitions ──────────────────────────────────────────────
    // Key = current status, Value = array of statuses it CAN move to.
    const TRANSITIONS = [
        self::PENDING         => [ self::APPROVED, self::CONFIRMED, self::DROPPED, self::CANCELLED ],
        self::APPROVED        => [ self::CONFIRMED, self::PAYMENT_EXPIRED, self::CANCELLED ],
        self::CONFIRMED       => [ self::REFUNDED, self::CANCELLED ],
        self::DROPPED         => [ self::PENDING ],   // Re-booking attempt
        self::PAYMENT_EXPIRED => [ self::PENDING ],   // Guest contacts to rebook
        self::CANCELLED       => [],                  // Terminal state
        self::REFUNDED        => [],                  // Terminal state
    ];

    /**
     * Check if a transition is allowed.
     */
    public static function can_transition( string $from, string $to ): bool {
        if ( ! isset( self::TRANSITIONS[ $from ] ) ) {
            return false;
        }
        return in_array( $to, self::TRANSITIONS[ $from ], true );
    }

    /**
     * Get all valid statuses.
     */
    public static function all(): array {
        return [
            self::PENDING,
            self::APPROVED,
            self::CONFIRMED,
            self::DROPPED,
            self::PAYMENT_EXPIRED,
            self::CANCELLED,
            self::REFUNDED,
        ];
    }
    /**
     * Get hex color for a status badge.
     */
    public static function get_color( string $status ): string {
        switch ( $status ) {
            case self::APPROVED:
            case self::CONFIRMED:
                return '#10b981'; // Green
            case self::PENDING:
                return '#f59e0b'; // Orange
            case self::DROPPED:
            case self::PAYMENT_EXPIRED:
            case self::CANCELLED:
                return '#ef4444'; // Red
            case self::REFUNDED:
                return '#6366f1'; // Indigo
            default:
                return '#94a3b8'; // Slate
        }
    }
}
