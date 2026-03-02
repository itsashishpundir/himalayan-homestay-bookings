<?php
/**
 * Financial Reports Admin Page
 *
 * Monthly revenue, commissions, refunds with date-range filter and CSV export.
 *
 * @package Himalayan\Homestay\Interface\Admin
 */

namespace Himalayan\Homestay\Interface\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FinancialReportsPage {

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'handle_csv_export' ] );
    }

    public static function register_menu(): void {
        add_submenu_page(
            'edit.php?post_type=hhb_homestay',
            __( 'Financial Reports', 'himalayan-homestay-bookings' ),
            '📊 ' . __( 'Reports', 'himalayan-homestay-bookings' ),
            'manage_options',
            'hhb-reports',
            [ __CLASS__, 'render_page' ]
        );
    }

    /**
     * Handle CSV export download.
     */
    public static function handle_csv_export(): void {
        if ( ! isset( $_GET['hhb_export_csv'] ) || ! current_user_can( 'manage_options' ) ) return;
        check_admin_referer( 'hhb_csv_export' );

        $from = sanitize_text_field( $_GET['from'] ?? gmdate( 'Y-m-01' ) );
        $to   = sanitize_text_field( $_GET['to'] ?? gmdate( 'Y-m-d' ) );
        $data = self::get_report_data( $from, $to );

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="hhb-report-' . $from . '-to-' . $to . '.csv"' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'Month', 'Total Revenue', 'Commission', 'Host Payouts', 'Refunds', 'Net Revenue', 'Bookings' ] );

        foreach ( $data['monthly'] as $row ) {
            fputcsv( $out, [
                $row->month,
                $row->total_revenue,
                $row->total_commission,
                $row->total_host_payout,
                $row->total_refunds,
                $row->net_revenue,
                $row->booking_count,
            ] );
        }

        fputcsv( $out, [] );
        fputcsv( $out, [
            'TOTALS',
            $data['summary']->total_revenue,
            $data['summary']->total_commission,
            $data['summary']->total_host_payout,
            $data['summary']->total_refunds,
            $data['summary']->net_revenue,
            $data['summary']->booking_count,
        ] );

        fclose( $out );
        exit;
    }

    /**
     * Query report data for a date range.
     */
    private static function get_report_data( string $from, string $to ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_bookings';

        $monthly = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                DATE_FORMAT(created_at, '%%Y-%%m') AS month,
                COUNT(*) AS booking_count,
                SUM(total_price) AS total_revenue,
                SUM(admin_commission) AS total_commission,
                SUM(host_payout) AS total_host_payout,
                SUM(refund_amount) AS total_refunds,
                SUM(total_price) - SUM(refund_amount) AS net_revenue
             FROM {$table}
             WHERE status IN ('confirmed', 'refunded', 'completed', 'checked_in')
               AND created_at >= %s
               AND created_at <= %s
             GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
             ORDER BY month DESC",
            $from . ' 00:00:00',
            $to . ' 23:59:59'
        ) );

        $summary = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COUNT(*) AS booking_count,
                COALESCE(SUM(total_price), 0) AS total_revenue,
                COALESCE(SUM(admin_commission), 0) AS total_commission,
                COALESCE(SUM(host_payout), 0) AS total_host_payout,
                COALESCE(SUM(refund_amount), 0) AS total_refunds,
                COALESCE(SUM(total_price) - SUM(refund_amount), 0) AS net_revenue
             FROM {$table}
             WHERE status IN ('confirmed', 'refunded', 'completed', 'checked_in')
               AND created_at >= %s
               AND created_at <= %s",
            $from . ' 00:00:00',
            $to . ' 23:59:59'
        ) );

        return [ 'monthly' => $monthly, 'summary' => $summary ];
    }

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $from = sanitize_text_field( $_GET['from'] ?? gmdate( 'Y-01-01' ) );
        $to   = sanitize_text_field( $_GET['to'] ?? gmdate( 'Y-m-d' ) );
        $data = self::get_report_data( $from, $to );
        $s    = $data['summary'];

        $csv_url = wp_nonce_url(
            add_query_arg( [ 'hhb_export_csv' => 1, 'from' => $from, 'to' => $to ], admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-reports' ) ),
            'hhb_csv_export'
        );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Financial Reports', 'himalayan-homestay-bookings' ); ?></h1>

            <!-- Date Filter -->
            <form method="get" style="display:flex; gap:12px; align-items:end; margin:20px 0; background:#fff; padding:16px 20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.06);">
                <input type="hidden" name="post_type" value="hhb_homestay" />
                <input type="hidden" name="page" value="hhb-reports" />
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#555; margin-bottom:4px;">From</label>
                    <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>" class="regular-text" style="padding:6px 10px;" />
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#555; margin-bottom:4px;">To</label>
                    <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>" class="regular-text" style="padding:6px 10px;" />
                </div>
                <button type="submit" class="button button-primary" style="height:36px;">Filter</button>
                <a href="<?php echo esc_url( $csv_url ); ?>" class="button" style="height:36px; line-height:34px;">📥 Export CSV</a>
            </form>

            <!-- Summary Cards -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:14px; margin:20px 0;">
                <div style="background:#fff; padding:18px; border-radius:8px; border-left:4px solid #10b981; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <div style="font-size:12px; color:#065f46; font-weight:700; text-transform:uppercase;">Total Revenue</div>
                    <div style="font-size:26px; font-weight:700; color:#111; margin-top:4px;">₹<?php echo number_format( (float) $s->total_revenue ); ?></div>
                </div>
                <div style="background:#fff; padding:18px; border-radius:8px; border-left:4px solid #6366f1; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <div style="font-size:12px; color:#3730a3; font-weight:700; text-transform:uppercase;">Commission</div>
                    <div style="font-size:26px; font-weight:700; color:#111; margin-top:4px;">₹<?php echo number_format( (float) $s->total_commission ); ?></div>
                </div>
                <div style="background:#fff; padding:18px; border-radius:8px; border-left:4px solid #f59e0b; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <div style="font-size:12px; color:#92400e; font-weight:700; text-transform:uppercase;">Host Payouts</div>
                    <div style="font-size:26px; font-weight:700; color:#111; margin-top:4px;">₹<?php echo number_format( (float) $s->total_host_payout ); ?></div>
                </div>
                <div style="background:#fff; padding:18px; border-radius:8px; border-left:4px solid #ef4444; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <div style="font-size:12px; color:#991b1b; font-weight:700; text-transform:uppercase;">Refunds</div>
                    <div style="font-size:26px; font-weight:700; color:#111; margin-top:4px;">₹<?php echo number_format( (float) $s->total_refunds ); ?></div>
                </div>
                <div style="background:#fff; padding:18px; border-radius:8px; border-left:4px solid #0ea5e9; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <div style="font-size:12px; color:#0c4a6e; font-weight:700; text-transform:uppercase;">Net Revenue</div>
                    <div style="font-size:26px; font-weight:700; color:#111; margin-top:4px;">₹<?php echo number_format( (float) $s->net_revenue ); ?></div>
                </div>
                <div style="background:#fff; padding:18px; border-radius:8px; border-left:4px solid #8b5cf6; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <div style="font-size:12px; color:#5b21b6; font-weight:700; text-transform:uppercase;">Bookings</div>
                    <div style="font-size:26px; font-weight:700; color:#111; margin-top:4px;"><?php echo intval( $s->booking_count ); ?></div>
                </div>
            </div>

            <!-- Monthly Breakdown -->
            <h3 style="margin-top:30px;"><?php esc_html_e( 'Monthly Breakdown', 'himalayan-homestay-bookings' ); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th style="text-align:right;">Revenue</th>
                        <th style="text-align:right;">Commission</th>
                        <th style="text-align:right;">Host Payouts</th>
                        <th style="text-align:right;">Refunds</th>
                        <th style="text-align:right;">Net Revenue</th>
                        <th style="text-align:center;">Bookings</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $data['monthly'] ) ) : ?>
                        <tr><td colspan="7" style="text-align:center; padding:30px; color:#999;">No data for this date range.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $data['monthly'] as $row ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( $row->month ); ?></strong></td>
                                <td style="text-align:right;">₹<?php echo number_format( (float) $row->total_revenue ); ?></td>
                                <td style="text-align:right;">₹<?php echo number_format( (float) $row->total_commission ); ?></td>
                                <td style="text-align:right;">₹<?php echo number_format( (float) $row->total_host_payout ); ?></td>
                                <td style="text-align:right; color:#ef4444;">₹<?php echo number_format( (float) $row->total_refunds ); ?></td>
                                <td style="text-align:right; font-weight:700;">₹<?php echo number_format( (float) $row->net_revenue ); ?></td>
                                <td style="text-align:center;"><?php echo intval( $row->booking_count ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
