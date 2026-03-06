<?php
/**
 * Host Dashboard — Bookings View (Decoupled REST API Client)
 *
 * All data is fetched via WordPress REST API from the Plugin backend.
 *
 * @package Himalayan\Homestay
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>

<style>
.hhb-table-container { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
.hhb-table { width: 100%; border-collapse: collapse; text-align: left; }
.hhb-table th { background: #f8fafc; padding: 16px 24px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.5px;}
.hhb-table td { padding: 16px 24px; border-bottom: 1px solid #e2e8f0; color: #334155; font-size: 14px; }
.hhb-table tr:hover { background: #f8fafc; }
.hhb-table tr:last-child td { border-bottom: none; }
.hhb-badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.hhb-pagination { display: flex; gap: 8px; justify-content: flex-end; margin-top: 24px; }
.hhb-page-link { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; color: #334155; text-decoration: none; font-size: 14px; font-weight: 500; background: #fff; cursor: pointer; }
.hhb-page-link.active { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
.hhb-loading-shimmer { animation: pk-shimmer 1.5s infinite linear; background: linear-gradient(to right, #f1f5f9 4%, #e2e8f0 25%, #f1f5f9 36%); background-size: 1000px 100%; height: 20px; border-radius: 4px; }
@keyframes pk-shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
</style>

<div id="hhb-bookings-wrapper">
    <div class="hhb-table-container">
        <table class="hhb-table">
            <thead>
                <tr>
                    <th>Booking ID</th>
                    <th>Guest</th>
                    <th>Property</th>
                    <th>Stay Dates</th>
                    <th>Status</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody id="hhb-bookings-body">
                <tr><td colspan="6"><div class="hhb-loading-shimmer" style="width: 100%; height: 40px; border-radius: 8px;"></div></td></tr>
                <tr><td colspan="6"><div class="hhb-loading-shimmer" style="width: 100%; height: 40px; border-radius: 8px;"></div></td></tr>
            </tbody>
        </table>
    </div>
    <div id="hhb-pagination-container" class="hhb-pagination"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let currentPage = new URLSearchParams(window.location.search).get('paged') || 1;
    loadBookings(currentPage);
});

async function loadBookings(page) {
    const tbody = document.getElementById('hhb-bookings-body');
    const pagin = document.getElementById('hhb-pagination-container');
    const nonce = '<?php echo wp_create_nonce("wp_rest"); ?>';

    tbody.innerHTML = `<tr><td colspan="6"><div class="hhb-loading-shimmer" style="width: 100%; height: 40px; border-radius: 8px;"></div></td></tr>`;

    try {
        const res = await fetch(`/wp-json/hhb/v1/host/bookings?page=${page}`, {
            headers: { 'X-WP-Nonce': nonce },
            credentials: 'same-origin'
        });
        const data = await res.json();

        if ( ! data.bookings || data.bookings.length === 0 ) {
            document.getElementById('hhb-bookings-wrapper').innerHTML = `
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:40px; text-align:center;">
                    <span class="material-symbols-outlined" style="font-size:48px; color:#cbd5e1; margin-bottom:16px;">receipt_long</span>
                    <h2 style="margin:0 0 8px; color:#1e293b; font-size:20px;">No Bookings Yet</h2>
                    <p style="color:#64748b; margin-top:0;">No bookings were found for this criteria.</p>
                </div>
            `;
            return;
        }

        tbody.innerHTML = data.bookings.map(b => `
            <tr>
                <td style="color: #64748b; font-family: monospace;">#${b.id}</td>
                <td>
                    <strong style="display:block; color:#0f172a; margin-bottom:2px;">${b.customer_name}</strong>
                    <div style="font-size:12px; color:#64748b; display:flex; gap:12px;">
                        <span>${b.guests} Guests</span>
                        <span><a href="mailto:${b.customer_email}" style="color:inherit;text-decoration:none;">${b.customer_email}</a></span>
                    </div>
                </td>
                <td>${b.property_name}</td>
                <td>
                    <div style="font-weight: 500;">${new Date(b.check_in).toLocaleDateString('en-US', {month:'short', day:'numeric'})} - ${new Date(b.check_out).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'})}</div>
                    <div style="font-size: 12px; color: #64748b;">${b.nights} Nights</div>
                </td>
                <td>
                    <span class="hhb-badge" style="color: ${b.status_color}; background-color: ${b.status_color}20;">${b.status_label}</span>
                </td>
                <td style="text-align: right; font-weight: 600; color:#0f172a;">${b.formatted_price}</td>
            </tr>
        `).join('');

        pagin.innerHTML = '';
        if ( data.total_pages > 1 ) {
            for ( let i = 1; i <= data.total_pages; i++ ) {
                pagin.innerHTML += `<button class="hhb-page-link ${parseInt(data.current_page) === i ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
            }
        }

    } catch (err) {
        document.getElementById('hhb-bookings-wrapper').innerHTML = `<div style="padding:20px; background:#fee2e2; color:#991b1b; border-radius:8px;">Failed to load bookings.</div>`;
    }
}

function changePage(page) {
    const url = new URL(window.location);
    url.searchParams.set('paged', page);
    window.history.pushState({}, '', url);
    loadBookings(page);
}
</script>
