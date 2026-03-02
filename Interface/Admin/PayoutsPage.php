<?php
/**
 * Host Payouts Admin Page
 *
 * Lists pending/completed payouts per host with mark-as-paid functionality.
 *
 * @package Himalayan\Homestay\Interface\Admin
 */

namespace Himalayan\Homestay\Interface\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PayoutsPage {

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'handle_actions' ] );
    }

    public static function register_menu(): void {
        add_submenu_page(
            'edit.php?post_type=hhb_homestay',
            __( 'Host Payouts', 'himalayan-homestay-bookings' ),
            '💰 ' . __( 'Payouts', 'himalayan-homestay-bookings' ),
            'manage_options',
            'hhb-payouts',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function handle_actions(): void {
        if ( ! isset( $_POST['hhb_mark_paid'] ) || ! current_user_can( 'manage_options' ) ) return;

        check_admin_referer( 'hhb_mark_payout_paid' );

        $payout_id = intval( $_POST['payout_id'] ?? 0 );
        if ( ! $payout_id ) return;

        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_payouts';

        $wpdb->update(
            $table,
            [
                'payout_status' => 'paid',
                'paid_at'       => current_time( 'mysql' ),
                'paid_by'       => get_current_user_id(),
                'notes'         => sanitize_text_field( $_POST['payout_notes'] ?? '' ),
            ],
            [ 'id' => $payout_id, 'payout_status' => 'pending' ],
            [ '%s', '%s', '%d', '%s' ],
            [ '%d', '%s' ]
        );

        wp_redirect( add_query_arg( 'marked_paid', 1, admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-payouts' ) ) );
        exit;
    }

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_payouts';
        $filter = sanitize_text_field( $_GET['status'] ?? 'pending' );

        $where = $filter === 'all' ? '' : $wpdb->prepare( 'WHERE p.payout_status = %s', $filter );

        $payouts = $wpdb->get_results(
            "SELECT p.*, b.customer_name, b.check_in, b.check_out, b.status AS booking_status,
                    hs.post_title AS homestay_name
             FROM {$table} p
             LEFT JOIN {$wpdb->prefix}himalayan_bookings b ON p.booking_id = b.id
             LEFT JOIN {$wpdb->posts} hs ON p.homestay_id = hs.ID
             {$where}
             ORDER BY p.created_at DESC
             LIMIT 200"
        );

        // Summary totals
        $totals = $wpdb->get_row(
            "SELECT
                COUNT(*) AS total_count,
                SUM(CASE WHEN payout_status = 'pending' THEN host_payout_amount ELSE 0 END) AS pending_total,
                SUM(CASE WHEN payout_status = 'paid' THEN host_payout_amount ELSE 0 END) AS paid_total,
                SUM(commission_amount) AS commission_total
             FROM {$table}"
        );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Host Payouts', 'himalayan-homestay-bookings' ); ?></h1>

            <?php if ( isset( $_GET['marked_paid'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><strong>✅ Payout marked as paid.</strong></p></div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin:20px 0;">
                <div style="background:#fff; padding:20px; border-radius:8px; border-left:4px solid #f59e0b; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <div style="font-size:13px; color:#92400e; font-weight:600; text-transform:uppercase;">Pending Payouts</div>
                    <div style="font-size:28px; font-weight:700; color:#111; margin-top:4px;">₹<?php echo number_format( (float) ( $totals->pending_total ?? 0 ) ); ?></div>
                </div>
                <div style="background:#fff; padding:20px; border-radius:8px; border-left:4px solid #10b981; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <div style="font-size:13px; color:#065f46; font-weight:600; text-transform:uppercase;">Paid Out</div>
                    <div style="font-size:28px; font-weight:700; color:#111; margin-top:4px;">₹<?php echo number_format( (float) ( $totals->paid_total ?? 0 ) ); ?></div>
                </div>
                <div style="background:#fff; padding:20px; border-radius:8px; border-left:4px solid #6366f1; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <div style="font-size:13px; color:#3730a3; font-weight:600; text-transform:uppercase;">Commission Earned</div>
                    <div style="font-size:28px; font-weight:700; color:#111; margin-top:4px;">₹<?php echo number_format( (float) ( $totals->commission_total ?? 0 ) ); ?></div>
                </div>
            </div>

            <!-- Status Tabs -->
            <ul class="subsubsub" style="margin-bottom:16px;">
                <li><a href="?post_type=hhb_homestay&page=hhb-payouts&status=pending" class="<?php echo $filter === 'pending' ? 'current' : ''; ?>">Pending</a> |</li>
                <li><a href="?post_type=hhb_homestay&page=hhb-payouts&status=paid" class="<?php echo $filter === 'paid' ? 'current' : ''; ?>">Paid</a> |</li>
                <li><a href="?post_type=hhb_homestay&page=hhb-payouts&status=all" class="<?php echo $filter === 'all' ? 'current' : ''; ?>">All</a></li>
            </ul>

            <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
                <thead>
                    <tr>
                        <th style="width:50px;">ID</th>
                        <th>Booking</th>
                        <th>Property</th>
                        <th>Host</th>
                        <th>Dates</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:right;">Commission</th>
                        <th style="text-align:right;">Host Payout</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $payouts ) ) : ?>
                        <tr><td colspan="10" style="text-align:center; padding:30px; color:#999;">No payouts found.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $payouts as $p ) :
                            $host = get_userdata( $p->host_id );
                            $host_name = $host ? $host->display_name : 'Unknown';
                        ?>
                            <tr>
                                <td>#<?php echo intval( $p->id ); ?></td>
                                <td><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-bookings&action=view&booking_id=' . $p->booking_id ) ); ?>">#<?php echo intval( $p->booking_id ); ?></a></td>
                                <td><?php echo esc_html( $p->homestay_name ?? 'N/A' ); ?></td>
                                <td><?php echo esc_html( $host_name ); ?></td>
                                <td><?php echo esc_html( ( $p->check_in ?? '' ) . ' → ' . ( $p->check_out ?? '' ) ); ?></td>
                                <td style="text-align:right;">₹<?php echo number_format( (float) $p->total_amount ); ?></td>
                                <td style="text-align:right;">₹<?php echo number_format( (float) $p->commission_amount ); ?></td>
                                <td style="text-align:right; font-weight:700;">₹<?php echo number_format( (float) $p->host_payout_amount ); ?></td>
                                <td>
                                    <?php if ( $p->payout_status === 'paid' ) : ?>
                                        <span style="background:#d1fae5; color:#065f46; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;">PAID</span>
                                        <br><small style="color:#999;"><?php echo esc_html( $p->paid_at ); ?></small>
                                    <?php else : ?>
                                        <span style="background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;">PENDING</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $p->payout_status === 'pending' ) : ?>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Mark this payout of ₹<?php echo number_format( (float) $p->host_payout_amount ); ?> as paid?');">
                                            <?php wp_nonce_field( 'hhb_mark_payout_paid' ); ?>
                                            <input type="hidden" name="payout_id" value="<?php echo intval( $p->id ); ?>" />
                                            <input type="hidden" name="payout_notes" value="" />
                                            <button type="submit" name="hhb_mark_paid" class="button button-primary button-small">✅ Mark Paid</button>
                                        </form>
                                    <?php else : ?>
                                        <em style="color:#999;">—</em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
