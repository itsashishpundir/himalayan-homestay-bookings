<?php
/**
 * Host Payouts Admin Page
 *
 * Lists pending/completed payouts per host with mark-as-paid,
 * delete, and auto-sync functionality.
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
        add_action( 'admin_post_hhb_mark_payout_paid',   [ __CLASS__, 'handle_mark_paid' ] );
        add_action( 'admin_post_hhb_delete_payout',      [ __CLASS__, 'handle_delete' ] );
        add_action( 'admin_post_hhb_sync_payout_status', [ __CLASS__, 'handle_sync' ] );
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

    // ── Mark as Paid ─────────────────────────────────────────────────────────
    public static function handle_mark_paid(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'hhb_mark_payout_paid' );

        $payout_id = intval( $_POST['payout_id'] ?? 0 );
        if ( $payout_id ) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'himalayan_payouts',
                [
                    'payout_status' => 'paid',
                    'paid_at'       => current_time( 'mysql' ),
                    'paid_by'       => get_current_user_id(),
                    'notes'         => sanitize_text_field( $_POST['payout_notes'] ?? '' ),
                ],
                [ 'id' => $payout_id ],
                [ '%s', '%s', '%d', '%s' ],
                [ '%d' ]
            );
        }

        wp_redirect( add_query_arg( 'marked_paid', 1, admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-payouts' ) ) );
        exit;
    }

    // ── Delete Payout Record ─────────────────────────────────────────────────
    public static function handle_delete(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'hhb_delete_payout' );

        $payout_id = intval( $_POST['payout_id'] ?? 0 );
        if ( $payout_id ) {
            global $wpdb;
            $wpdb->delete( $wpdb->prefix . 'himalayan_payouts', [ 'id' => $payout_id ], [ '%d' ] );
        }

        wp_redirect( add_query_arg( 'deleted', 1, admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-payouts' ) ) );
        exit;
    }

    // ── Sync: mark payout paid if booking is confirmed ───────────────────────
    public static function handle_sync(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'hhb_sync_payout_status' );

        global $wpdb;
        // Auto-mark payouts as "paid" for bookings whose status is already confirmed
        $wpdb->query(
            "UPDATE {$wpdb->prefix}himalayan_payouts p
             INNER JOIN {$wpdb->prefix}himalayan_bookings b ON p.booking_id = b.id
             SET p.payout_status = 'paid', p.paid_at = NOW()
             WHERE b.status = 'confirmed' AND p.payout_status = 'pending'"
        );

        wp_redirect( add_query_arg( 'synced', 1, admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-payouts' ) ) );
        exit;
    }

    // ── Render ────────────────────────────────────────────────────────────────
    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        global $wpdb;
        $table  = $wpdb->prefix . 'himalayan_payouts';
        $filter = sanitize_text_field( $_GET['status'] ?? 'pending' );

        $where = $filter === 'all' ? '' : $wpdb->prepare( 'WHERE p.payout_status = %s', $filter );

        $payouts = $wpdb->get_results(
            "SELECT p.*, b.customer_name, b.check_in, b.check_out, b.status AS booking_status, b.gateway,
                    hs.post_title AS homestay_name
             FROM {$table} p
             LEFT JOIN {$wpdb->prefix}himalayan_bookings b ON p.booking_id = b.id
             LEFT JOIN {$wpdb->posts} hs ON p.homestay_id = hs.ID
             {$where}
             ORDER BY p.created_at DESC
             LIMIT 200"
        );

        $totals = $wpdb->get_row(
            "SELECT
                COUNT(*) AS total_count,
                SUM(CASE WHEN payout_status = 'pending' THEN host_payout_amount ELSE 0 END) AS pending_total,
                SUM(CASE WHEN payout_status = 'paid'    THEN host_payout_amount ELSE 0 END) AS paid_total,
                SUM(commission_amount) AS commission_total
             FROM {$table}"
        );

        $redirect_base = admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-payouts' );
        ?>
        <div class="wrap">
            <h1 style="display:flex;align-items:center;gap:12px;">
                <?php esc_html_e( 'Host Payouts', 'himalayan-homestay-bookings' ); ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
                    <?php wp_nonce_field( 'hhb_sync_payout_status' ); ?>
                    <input type="hidden" name="action" value="hhb_sync_payout_status" />
                    <button type="submit" class="button button-secondary" style="font-size:12px;"
                            onclick="return confirm('Auto-mark payouts as Paid for all Confirmed bookings?');">
                        🔄 Sync from Bookings
                    </button>
                </form>
            </h1>

            <?php if ( isset( $_GET['marked_paid'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><strong>✅ Payout marked as paid.</strong></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['deleted'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><strong>🗑️ Payout record deleted.</strong></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['synced'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><strong>🔄 Payouts synced from booking statuses.</strong></p></div>
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
                <li><a href="?post_type=hhb_homestay&page=hhb-payouts&status=paid"    class="<?php echo $filter === 'paid'    ? 'current' : ''; ?>">Paid</a> |</li>
                <li><a href="?post_type=hhb_homestay&page=hhb-payouts&status=all"     class="<?php echo $filter === 'all'     ? 'current' : ''; ?>">All</a></li>
            </ul>

            <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
                <thead>
                    <tr>
                        <th style="width:40px;">ID</th>
                        <th>Booking</th>
                        <th>Property</th>
                        <th>Host</th>
                        <th>Dates</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:right;">Commission</th>
                        <th style="text-align:right;">Host Payout</th>
                        <th>Booking Status</th>
                        <th>Payout Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $payouts ) ) : ?>
                        <tr><td colspan="11" style="text-align:center; padding:30px; color:#999;">No payouts found.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $payouts as $p ) :
                            $host      = get_userdata( $p->host_id );
                            $host_name = $host ? $host->display_name : 'Unknown';
                            $b_status  = $p->booking_status ?? 'unknown';
                            $b_colors  = [
                                'confirmed' => [ '#d1fae5', '#065f46' ],
                                'pending'   => [ '#fef3c7', '#92400e' ],
                                'approved'  => [ '#dbeafe', '#1e40af' ],
                                'cancelled' => [ '#fee2e2', '#991b1b' ],
                            ];
                            $bc = $b_colors[ $b_status ] ?? [ '#f1f5f9', '#475569' ];
                        ?>
                            <tr>
                                <td>#<?php echo intval( $p->id ); ?></td>
                                <td><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-bookings&action=view&booking_id=' . $p->booking_id ) ); ?>">#<?php echo intval( $p->booking_id ); ?></a></td>
                                <td><?php echo esc_html( $p->homestay_name ?? 'N/A' ); ?></td>
                                <td><?php echo esc_html( $host_name ); ?></td>
                                <td><?php echo esc_html( ( $p->check_in ?? '—' ) . ' → ' . ( $p->check_out ?? '—' ) ); ?></td>
                                <td style="text-align:right;">₹<?php echo number_format( (float) $p->total_amount ); ?></td>
                                <td style="text-align:right;">₹<?php echo number_format( (float) $p->commission_amount ); ?></td>
                                <td style="text-align:right; font-weight:700;">₹<?php echo number_format( (float) $p->host_payout_amount ); ?></td>
                                <td>
                                    <span style="background:<?php echo esc_attr( $bc[0] ); ?>; color:<?php echo esc_attr( $bc[1] ); ?>; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:600; text-transform:uppercase;">
                                        <?php echo esc_html( $b_status ); ?>
                                        <?php if ( $p->gateway ) : ?>
                                            <small>(<?php echo esc_html( ucfirst( $p->gateway ) ); ?>)</small>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ( $p->payout_status === 'paid' ) : ?>
                                        <span style="background:#d1fae5; color:#065f46; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;">PAID</span>
                                        <br><small style="color:#999;"><?php echo esc_html( $p->paid_at ); ?></small>
                                    <?php elseif ( $p->payout_status === 'cancelled' ) : ?>
                                        <span style="background:#fee2e2; color:#991b1b; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;">CANCELLED</span>
                                    <?php else : ?>
                                        <span style="background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;">PENDING</span>
                                    <?php endif; ?>
                                </td>
                                <td style="display:flex;gap:4px;flex-wrap:wrap;align-items:center;">
                                    <?php if ( $p->payout_status === 'pending' ) : ?>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;"
                                              onsubmit="return confirm('Mark payout of ₹<?php echo number_format( (float) $p->host_payout_amount ); ?> as paid?');">
                                            <?php wp_nonce_field( 'hhb_mark_payout_paid' ); ?>
                                            <input type="hidden" name="action"     value="hhb_mark_payout_paid" />
                                            <input type="hidden" name="payout_id"  value="<?php echo intval( $p->id ); ?>" />
                                            <input type="hidden" name="payout_notes" value="" />
                                            <button type="submit" class="button button-primary button-small">✅ Mark Paid</button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Delete button (always visible) -->
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;"
                                          onsubmit="return confirm('Delete this payout record permanently?');">
                                        <?php wp_nonce_field( 'hhb_delete_payout' ); ?>
                                        <input type="hidden" name="action"    value="hhb_delete_payout" />
                                        <input type="hidden" name="payout_id" value="<?php echo intval( $p->id ); ?>" />
                                        <button type="submit" class="button button-small" style="color:#dc2626;border-color:#dc2626;">🗑️ Delete</button>
                                    </form>
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
