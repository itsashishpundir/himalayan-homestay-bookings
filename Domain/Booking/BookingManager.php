<?php
/**
 * Booking Manager
 *
 * Handles the creation and lifecycle management of booking records,
 * including deposit tracking and state-machine–controlled status transitions.
 *
 * @package Himalayan\Homestay\Domain\Booking
 */

namespace Himalayan\Homestay\Domain\Booking;

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/BookingStatus.php';

class BookingManager {

    /**
     * Create a new booking record.
     *
     * @param array $data Booking data.
     * @return int|false Booking ID on success, false on failure.
     */
    public function create_booking( array $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_bookings';

        $inserted = $wpdb->insert( $table, [
            'homestay_id'    => $data['homestay_id'],
            'customer_name'  => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'customer_phone' => $data['customer_phone'] ?? '',
            'check_in'       => $data['check_in'],
            'check_out'      => $data['check_out'],
            'guests'         => $data['guests'] ?? 1,
            'adults'         => $data['adults'] ?? $data['guests'] ?? 1,
            'children'       => $data['children'] ?? 0,
            'total_price'    => $data['total_price'],
            'admin_commission' => $data['admin_commission'] ?? 0,
            'host_payout'    => $data['host_payout'] ?? $data['total_price'],
            'deposit_amount' => $data['deposit_amount'] ?? $data['total_price'],
            'balance_due'    => $data['balance_due'] ?? 0,
            'status'             => BookingStatus::PENDING,
            'payment_token'      => wp_generate_password( 32, false ),
            'payment_expires_at' => ( isset( $data['payment_mode'] ) && $data['payment_mode'] === 'Cash' ) ? null : gmdate( 'Y-m-d H:i:s', time() + 30 * MINUTE_IN_SECONDS ),
            'gateway'            => $data['payment_mode'] ?? '',
            'notes'              => $data['notes'] ?? '',
        ] );

        if ( $inserted ) {
            $booking_id = $wpdb->insert_id;
            do_action( 'himalayan_booking_created', $booking_id );
            return $booking_id;
        }

        return false;
    }

    // =========================================================================
    // Central State Machine
    // =========================================================================

    /**
     * Transition a booking to a new status through the state machine.
     *
     * All status changes MUST go through this method. It enforces the
     * allowed transitions defined in BookingStatus::TRANSITIONS, logs
     * the transition for auditing, and fires the appropriate action hook.
     *
     * @param int    $booking_id The booking ID.
     * @param string $new_status The target status (use BookingStatus constants).
     * @param string $actor      Who initiated: 'admin', 'webhook', 'cron', 'system', 'customer'.
     * @param array  $extra_data Optional additional columns to update atomically (e.g. transaction_id, refund_id).
     *
     * @return bool  True on success.
     * @throws \Exception If the transition is invalid or the booking does not exist.
     */
    public function transition_status( int $booking_id, string $new_status, string $actor = 'system', array $extra_data = [] ): bool {
        global $wpdb;
        $table   = $wpdb->prefix . 'himalayan_bookings';
        $booking = $this->get_booking( $booking_id );

        if ( ! $booking ) {
            $msg = sprintf( 'HHB State Machine: Booking #%d not found.', $booking_id );
            error_log( $msg );
            throw new \Exception( $msg );
        }

        $current_status = $booking->status;

        // ── Guard: Validate transition ───────────────────────────────────
        if ( ! BookingStatus::can_transition( $current_status, $new_status ) ) {
            $msg = sprintf(
                'HHB State Machine Violation: Attempted [%s → %s] on Booking #%d by [%s]. Blocked.',
                $current_status, $new_status, $booking_id, $actor
            );
            error_log( $msg );
            throw new \Exception( $msg );
        }

        // Terminal state lock: Refunded can never be overwritten by a late Confirmed webhook race condition
        if ( $current_status === BookingStatus::REFUNDED && $new_status === BookingStatus::CONFIRMED ) {
            error_log( sprintf( 'HHB Race Guard: Rejected late-arriving transition refunded -> confirmed for Booking #%d', $booking_id ) );
            return false;
        }

        // ── 1. START STRICT DB TRANSACTION ───────────────────────────────
        $wpdb->query( 'START TRANSACTION' );

        $update_data = array_merge( [ 'status' => $new_status ], $extra_data );

        $updated = $wpdb->update(
            $table,
            $update_data,
            [ 'id' => $booking_id, 'status' => $current_status ], // Optimistic lock
            null,
            [ '%d', '%s' ]
        );

        if ( ! $updated ) {
            $wpdb->query( 'ROLLBACK' );
            $msg = sprintf( 'HHB State Machine: UPDATE failed for Booking #%d [%s → %s]. Possible race condition.', $booking_id, $current_status, $new_status );
            error_log( $msg );
            return false;
        }

        // ── 2. Audit Log ─────────────────────────────────────────────────
        $wpdb->insert(
            $wpdb->prefix . 'himalayan_audit_log',
            [
                'booking_id' => $booking_id,
                'old_status' => $current_status,
                'new_status' => $new_status,
                'actor'      => $actor,
                'note'       => isset( $extra_data['refund_id'] ) ? sprintf( 'Refund ID: %s', $extra_data['refund_id'] ) : '',
            ],
            [ '%d', '%s', '%s', '%s', '%s' ]
        );

        // ── 3. Availability Ledger Synchronisation ───────────────────────
        $ledger_table = $wpdb->prefix . 'himalayan_availability_ledger';
        if ( $new_status === BookingStatus::CONFIRMED || $new_status === BookingStatus::APPROVED ) {
            // Reserve dates in ledger
            $period = new \DatePeriod( new \DateTime( $booking->check_in ), new \DateInterval( 'P1D' ), new \DateTime( $booking->check_out ) );
            foreach ( $period as $dt ) {
                $wpdb->query( $wpdb->prepare(
                    "INSERT IGNORE INTO {$ledger_table} (homestay_id, booking_id, date, status) VALUES (%d, %d, %s, %s)",
                    $booking->homestay_id, $booking_id, $dt->format( 'Y-m-d' ), $new_status
                ) );
            }
        } elseif ( in_array( $new_status, [ BookingStatus::CANCELLED, BookingStatus::REFUNDED, BookingStatus::DROPPED, BookingStatus::PAYMENT_EXPIRED ], true ) ) {
            // Drop dates from ledger (frees up availability instantly)
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$ledger_table} WHERE booking_id = %d", $booking_id ) );
        }

        // ── 4. Payout Table Synchronisation ──────────────────────────────
        if ( $new_status === BookingStatus::CONFIRMED ) {
            // Create payout row transactionally inside the state machine
            self::create_payout_record( $booking_id );
        } elseif ( $new_status === BookingStatus::REFUNDED || $new_status === BookingStatus::CANCELLED ) {
            // Cancel unpaid payouts instantly
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}himalayan_payouts SET payout_status = 'cancelled' WHERE booking_id = %d AND payout_status = 'pending'",
                $booking_id
            ) );
        }

        // ── 5. COMMIT TRANSACTION ────────────────────────────────────────
        $wpdb->query( 'COMMIT' );

        error_log( sprintf( 'HHB Transition: Booking #%d [%s → %s] by [%s] at %s', $booking_id, $current_status, $new_status, $actor, gmdate( 'Y-m-d H:i:s' ) ) );

        // ── 6. Fire hooks SAFELY OUTSIDE the transaction ─────────────────
        // Email sending, API calls, Invoice Generation, Webhooks
        $hook_map = [
            BookingStatus::APPROVED        => 'himalayan_booking_approved',
            BookingStatus::CONFIRMED       => 'himalayan_payment_confirmed',
            BookingStatus::DROPPED         => 'himalayan_booking_dropped',
            BookingStatus::PAYMENT_EXPIRED => 'himalayan_payment_expired',
            BookingStatus::CANCELLED       => 'himalayan_booking_cancelled',
            BookingStatus::REFUNDED        => 'himalayan_booking_refunded',
        ];

        if ( isset( $hook_map[ $new_status ] ) ) {
            do_action( $hook_map[ $new_status ], $booking_id );
        }

        return true;
    }

    // =========================================================================
    // Convenience Methods (all route through transition_status)
    // =========================================================================

    public function approve_booking( int $booking_id ): bool {
        return $this->transition_status( $booking_id, BookingStatus::APPROVED, 'admin' );
    }

    public function confirm_payment( int $booking_id, string $transaction_id = '', string $gateway = '' ): bool {
        return $this->transition_status( $booking_id, BookingStatus::CONFIRMED, 'system', [
            'transaction_id' => $transaction_id,
            'gateway'        => $gateway ?: null,
        ] );
    }

    public function cancel_booking( int $booking_id ): bool {
        return $this->transition_status( $booking_id, BookingStatus::CANCELLED, 'admin' );
    }

    public function drop_booking( int $booking_id ): bool {
        return $this->transition_status( $booking_id, BookingStatus::DROPPED, 'system' );
    }

    public function expire_payment( int $booking_id ): bool {
        return $this->transition_status( $booking_id, BookingStatus::PAYMENT_EXPIRED, 'cron' );
    }

    public function refund_booking( int $booking_id, string $refund_id, float $refund_amount ): bool {
        return $this->transition_status( $booking_id, BookingStatus::REFUNDED, 'admin', [
            'refund_id'     => $refund_id,
            'refund_amount' => $refund_amount,
            'refund_status' => 'processed',
            'refunded_at'   => gmdate( 'Y-m-d H:i:s' ),
        ] );
    }

    // =========================================================================
    // Read
    // =========================================================================

    /**
     * Get a single booking by ID.
     */
    public function get_booking( int $booking_id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}himalayan_bookings WHERE id = %d",
            $booking_id
        ) );
    }

    // =========================================================================
    // Payout Auto-Creation
    // =========================================================================

    public static function init_payout_hooks(): void {
        // Disabled: Payout ledger rows are now created natively within the 
        // transition_status() SQL transaction to guarantee financial integrity.
    }

    /**
     * Auto-create a pending payout record when a booking is confirmed.
     * Idempotent: INSERT IGNORE on UNIQUE KEY (booking_id).
     */
    public static function create_payout_record( int $booking_id ): void {
        global $wpdb;
        $table   = $wpdb->prefix . 'himalayan_payouts';
        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}himalayan_bookings WHERE id = %d",
            $booking_id
        ) );

        if ( ! $booking ) return;

        $host_id = get_post_field( 'post_author', $booking->homestay_id );
        if ( ! $host_id ) $host_id = 0;

        $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO {$table}
                (booking_id, host_id, homestay_id, total_amount, commission_amount, host_payout_amount, payout_status)
             VALUES (%d, %d, %d, %f, %f, %f, 'pending')",
            $booking_id,
            $host_id,
            $booking->homestay_id,
            $booking->total_price,
            $booking->admin_commission,
            $booking->host_payout
        ) );
    }
}
