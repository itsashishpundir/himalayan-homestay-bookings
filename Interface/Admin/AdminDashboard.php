<?php
/**
 * Admin Dashboard Widgets
 *
 * Adds charts and analytics to the main WP Admin Dashboard
 * for the Himalayan Homestay Bookings plugin.
 *
 * @package Himalayan\Homestay\Interface\Admin
 */

namespace Himalayan\Homestay\Interface\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AdminDashboard {

    public static function init() {
        add_action( 'wp_dashboard_setup', [ __CLASS__, 'add_dashboard_widgets' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
    }

    public static function enqueue_scripts( $hook ) {
        // Only load on the main dashboard
        if ( 'index.php' !== $hook ) {
            return;
        }

        // Load Chart.js from CDN
        wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.9.1', true );
    }

    public static function add_dashboard_widgets() {
        if ( current_user_can( 'manage_options' ) ) {
            wp_add_dashboard_widget(
                'hhb_analytics_widget',
                __( 'Himalayan Homestay Analytics', 'himalayan-homestay-bookings' ),
                [ __CLASS__, 'render_analytics_widget' ]
            );
        }
    }

    public static function render_analytics_widget() {
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'himalayan_bookings';

        // 1. Fetch Revenue for the Last 6 Months
        // Group by Year and Month
        $revenue_data = $wpdb->get_results( "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month_raw,
                DATE_FORMAT(created_at, '%b %Y') as month_label,
                SUM(total_price) as revenue,
                COUNT(id) as total_bookings
            FROM {$bookings_table}
            WHERE status IN ('confirmed', 'completed', 'approved')
            AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY month_raw
            ORDER BY month_raw ASC
        " );

        $labels   = [];
        $revenues = [];
        $counts   = [];

        foreach ( $revenue_data as $row ) {
            $labels[]   = $row->month_label;
            $revenues[] = (float) $row->revenue;
            $counts[]   = (int) $row->total_bookings;
        }

        // Prepare data for JS
        $chart_data = [
            'labels'   => $labels,
            'revenues' => $revenues,
            'counts'   => $counts
        ];

        // 2. Fetch Quick Stats
        $total_revenue = $wpdb->get_var( "SELECT SUM(total_price) FROM {$bookings_table} WHERE status IN ('confirmed', 'completed', 'approved')" ) ?: 0;
        $total_hosts   = count( get_users( ['role' => 'hhb_host'] ) );
        $active_props  = wp_count_posts( 'hhb_homestay' )->publish ?? 0;

        ?>
        <style>
            .hhb-admin-stats { display: flex; gap: 15px; margin-bottom: 20px; text-align: center; }
            .hhb-stat-box { flex: 1; background: #fff; border: 1px solid #ccd0d4; padding: 15px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
            .hhb-stat-val { font-size: 24px; font-weight: 600; color: #2271b1; display: block; margin-top: 5px; }
            .hhb-stat-label { font-size: 13px; color: #646970; text-transform: uppercase; }
        </style>
        
        <div class="hhb-admin-stats">
            <div class="hhb-stat-box">
                <span class="hhb-stat-label">Lifetime Revenue</span>
                <span class="hhb-stat-val"><?php echo '₹' . number_format( $total_revenue, 2 ); ?></span>
            </div>
            <div class="hhb-stat-box">
                <span class="hhb-stat-label">Active Hosts</span>
                <span class="hhb-stat-val"><?php echo number_format( $total_hosts ); ?></span>
            </div>
            <div class="hhb-stat-box">
                <span class="hhb-stat-label">Live Properties</span>
                <span class="hhb-stat-val"><?php echo number_format( $active_props ); ?></span>
            </div>
        </div>

        <div>
            <canvas id="hhbRevenueChart" width="100%" height="40"></canvas>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var ctx = document.getElementById('hhbRevenueChart');
                if (!ctx) return;
                
                var data = <?php echo json_encode( $chart_data ); ?>;
                
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Revenue (₹)',
                            data: data.revenues,
                            backgroundColor: 'rgba(34, 113, 177, 0.8)',
                            borderColor: 'rgba(34, 113, 177, 1)',
                            borderWidth: 1,
                            borderRadius: 4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Bookings',
                            data: data.counts,
                            type: 'line',
                            borderColor: '#d63638',
                            backgroundColor: '#d63638',
                            borderWidth: 2,
                            tension: 0.3,
                            yAxisID: 'y1'
                        }]
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            y: {
                                type: 'linear', display: true, position: 'left',
                                title: { display: true, text: 'Revenue' }
                            },
                            y1: {
                                type: 'linear', display: true, position: 'right',
                                title: { display: true, text: 'Number of Bookings' },
                                grid: { drawOnChartArea: false }
                            }
                        }
                    }
                });
            });
        </script>
        <?php
    }
}
