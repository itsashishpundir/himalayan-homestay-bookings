<?php
/**
 * Host Dashboard — Overview View (Decoupled REST API Client)
 *
 * All data is fetched via WordPress REST API from the Plugin backend.
 *
 * @package Himalayan\Homestay
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>

<style>
.hhb-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 32px; }
.hhb-stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 16px; }
.hhb-stat-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #eff6ff; color: #3b82f6; font-size: 24px; }
.hhb-stat-info h3 { margin: 0; font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
.hhb-stat-info p { margin: 4px 0 0; font-size: 24px; font-weight: 700; color: #0f172a; }
.hhb-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.hhb-table-container { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
.hhb-table { width: 100%; border-collapse: collapse; text-align: left; }
.hhb-table th { background: #f8fafc; padding: 16px 24px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.5px;}
.hhb-table td { padding: 16px 24px; border-bottom: 1px solid #e2e8f0; color: #334155; font-size: 14px; }
.hhb-table tr:last-child td { border-bottom: none; }
.hhb-loading-shimmer { animation: pk-shimmer 1.5s infinite linear; background: linear-gradient(to right, #f1f5f9 4%, #e2e8f0 25%, #f1f5f9 36%); background-size: 1000px 100%; height: 20px; border-radius: 4px; }
@keyframes pk-shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
</style>

<div id="hhb-dashboard-wrapper">
    <!-- Stat Cards -->
    <div class="hhb-stat-grid" id="hhb-stats-container">
        <div class="hhb-stat-card">
            <div class="hhb-stat-icon" style="background: #ecfeff; color: #06b6d4;">
                <span class="material-symbols-outlined">payments</span>
            </div>
            <div class="hhb-stat-info" style="flex:1;">
                <h3>Total Earnings</h3>
                <p id="stat-earnings" class="hhb-loading-shimmer" style="width: 80px;"></p>
            </div>
        </div>
        <div class="hhb-stat-card">
            <div class="hhb-stat-icon" style="background: #eff6ff; color: #3b82f6;">
                <span class="material-symbols-outlined">calendar_today</span>
            </div>
            <div class="hhb-stat-info" style="flex:1;">
                <h3>Active Bookings</h3>
                <p id="stat-active" class="hhb-loading-shimmer" style="width: 40px;"></p>
            </div>
        </div>
        <div class="hhb-stat-card">
            <div class="hhb-stat-icon" style="background: #fdf4ff; color: #d946ef;">
                <span class="material-symbols-outlined">trending_up</span>
            </div>
            <div class="hhb-stat-info" style="flex:1;">
                <h3>30-Day Occupancy</h3>
                <p id="stat-occupancy" class="hhb-loading-shimmer" style="width: 50px;"></p>
            </div>
        </div>
    </div>

    <!-- Recent Bookings Table -->
    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 16px 0;">Recent Bookings</h2>
    <div class="hhb-table-container">
        <table class="hhb-table">
            <thead>
                <tr>
                    <th>Guest Details</th>
                    <th>Property</th>
                    <th>Dates</th>
                    <th>Status</th>
                    <th style="text-align: right;">Payout</th>
                </tr>
            </thead>
            <tbody id="hhb-recent-bookings-body">
                <tr><td colspan="5"><div class="hhb-loading-shimmer" style="width: 100%; height: 40px; border-radius: 8px;"></div></td></tr>
                <tr><td colspan="5"><div class="hhb-loading-shimmer" style="width: 100%; height: 40px; border-radius: 8px;"></div></td></tr>
            </tbody>
        </table>
    </div>
    <div style="margin-top: 16px; text-align: right;" id="hhb-view-all-link">
        <a href="?view=bookings" style="color: #2563eb; font-weight: 600; text-decoration: none; font-size: 14px;">View All Bookings &rarr;</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const nonce = '<?php echo wp_create_nonce("wp_rest"); ?>';
        const res = await fetch('/wp-json/hhb/v1/host/overview', {
            headers: { 'X-WP-Nonce': nonce }
        });
        const data = await res.json();

        if ( data.has_properties === false ) {
            document.getElementById('hhb-dashboard-wrapper').innerHTML = `
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:40px; text-align:center;">
                    <span class="material-symbols-outlined" style="font-size:48px; color:#cbd5e1; margin-bottom:16px;">holiday_village</span>
                    <h2 style="margin:0 0 8px; color:#1e293b; font-size:20px;">Welcome to your Host Dashboard!</h2>
                    <p style="color:#64748b; margin-top:0;">${data.message || 'You don\'t have any active properties listed yet.'}</p>
                    <a href="<?php echo esc_url( add_query_arg( 'view', 'edit-property', $dashboard_url ) ); ?>" class="hhb-btn" style="display:inline-block; margin-top:16px; text-decoration:none;">+ Add Your First Property</a>
                </div>
            `;
            return;
        }

        const statEarnings = document.getElementById('stat-earnings');
        statEarnings.className = ''; statEarnings.style.width = 'auto'; statEarnings.innerText = data.stats.total_earnings;

        const statActive = document.getElementById('stat-active');
        statActive.className = ''; statActive.style.width = 'auto'; statActive.innerText = data.stats.active_bookings;

        const statOccupancy = document.getElementById('stat-occupancy');
        statOccupancy.className = ''; statOccupancy.style.width = 'auto'; statOccupancy.innerText = data.stats.occupancy_rate;

        const tbody = document.getElementById('hhb-recent-bookings-body');
        if ( data.recent_bookings && data.recent_bookings.length > 0 ) {
            tbody.innerHTML = data.recent_bookings.map(b => `
                <tr>
                    <td>
                        <strong style="display:block; color:#0f172a; margin-bottom:2px;">${b.customer_name}</strong>
                        <span style="font-size:12px; color:#64748b;">${b.guests} Guests</span>
                    </td>
                    <td>${b.property_name}</td>
                    <td>${b.date_range}</td>
                    <td><span class="hhb-badge" style="color: ${b.status_color}; background-color: ${b.status_color}20;">${b.status_label}</span></td>
                    <td style="text-align: right; font-weight: 600; color:#0f172a;">${b.formatted_price}</td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding: 40px; color:#64748b;">No recent bookings found.</td></tr>`;
        }

    } catch (err) {
        document.getElementById('hhb-dashboard-wrapper').innerHTML = `<div style="padding:20px; background:#fee2e2; color:#991b1b; border-radius:8px;">Failed to load dashboard data. Please try again later.</div>`;
    }
});
</script>
