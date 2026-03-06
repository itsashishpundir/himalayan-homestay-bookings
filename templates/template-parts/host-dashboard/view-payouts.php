<?php
/**
 * Host Dashboard: Payouts View
 *
 * @package Himalayan\Homestay
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>

<style>
.hhb-loading-shimmer { animation: pk-shimmer 1.5s infinite linear; background: linear-gradient(to right, #f1f5f9 4%, #e2e8f0 25%, #f1f5f9 36%); background-size: 1000px 100%; height: 20px; border-radius: 4px; }
@keyframes pk-shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
</style>

<div id="hhb-payouts-wrapper">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 32px;">
        <div style="background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; align-items: center;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: #ecfdf5; color: #10b981; display: flex; align-items: center; justify-content: center; margin-right: 16px;">
                <span class="material-symbols-outlined">payments</span>
            </div>
            <div>
                <p style="margin: 0; color: #64748b; font-size: 14px; font-weight: 500;">Lifetime Earnings</p>
                <div id="payout-lifetime" class="hhb-loading-shimmer" style="width: 100px; height: 28px; margin-top: 4px;"></div>
            </div>
        </div>
        <div style="background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; align-items: center;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: #eff6ff; color: #3b82f6; display: flex; align-items: center; justify-content: center; margin-right: 16px;">
                <span class="material-symbols-outlined">pending_actions</span>
            </div>
            <div>
                <p style="margin: 0; color: #64748b; font-size: 14px; font-weight: 500;">Expected Upcoming</p>
                <div id="payout-upcoming" class="hhb-loading-shimmer" style="width: 80px; height: 28px; margin-top: 4px;"></div>
            </div>
        </div>
    </div>

    <div style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden;">
        <div style="padding: 20px 24px; border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
            <h3 style="margin: 0; font-size: 16px; color: #0f172a; font-weight: 600;">Payout History</h3>
        </div>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="background: #f1f5f9; border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 12px 24px; font-weight: 600; color: #475569; font-size: 13px; text-transform: uppercase;">Booking</th>
                        <th style="padding: 12px 24px; font-weight: 600; color: #475569; font-size: 13px; text-transform: uppercase;">Guest</th>
                        <th style="padding: 12px 24px; font-weight: 600; color: #475569; font-size: 13px; text-transform: uppercase;">Dates</th>
                        <th style="padding: 12px 24px; font-weight: 600; color: #475569; font-size: 13px; text-transform: uppercase;">Gross Total</th>
                        <th style="padding: 12px 24px; font-weight: 600; color: #ef4444; font-size: 13px; text-transform: uppercase;">Admin Fee (10%)</th>
                        <th style="padding: 12px 24px; font-weight: 600; color: #10b981; font-size: 13px; text-transform: uppercase;">Your Payout</th>
                    </tr>
                </thead>
                <tbody id="hhb-payouts-body">
                    <tr><td colspan="6"><div class="hhb-loading-shimmer" style="width: 100%; height: 40px; border-radius: 8px;"></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const nonce = '<?php echo wp_create_nonce("wp_rest"); ?>';
        const data = await fetch('/wp-json/hhb/v1/host/payouts', { headers: { 'X-WP-Nonce': nonce } }).then(r => r.json());

        if ( ! data.has_properties ) {
            document.getElementById('hhb-payouts-wrapper').innerHTML = `
                <div style="background:#fff; padding:40px; text-align:center; border-radius:12px; border:1px solid #e2e8f0;">
                    <span class="material-symbols-outlined" style="font-size:48px; color:#cbd5e1;">account_balance_wallet</span>
                    <h3 style="margin:8px 0; color:#0f172a;">No Earnings Yet</h3>
                    <p style="color:#64748b;">Add a property and get your first booking to start earning!</p>
                </div>
            `;
            return;
        }

        const elLife = document.getElementById('payout-lifetime');
        elLife.className = ''; elLife.style = 'font-size:24px;color:#0f172a;font-weight:700;';
        elLife.innerText = data.lifetime_earnings;

        const elUp = document.getElementById('payout-upcoming');
        elUp.className = ''; elUp.style = 'font-size:24px;color:#0f172a;font-weight:700;';
        elUp.innerText = data.upcoming_payouts;

        const tbody = document.getElementById('hhb-payouts-body');
        if ( data.payout_history && data.payout_history.length > 0 ) {
            tbody.innerHTML = data.payout_history.map(row => `
                <tr style="border-bottom:1px solid #e2e8f0;${row.status==='cancelled'?'opacity:0.6;':''}">
                    <td style="padding:16px 24px;color:#0f172a;font-weight:500;">#${row.booking_id}<br><span style="font-size:12px;color:${row.status_color};">${row.status_label}</span></td>
                    <td style="padding:16px 24px;">${row.customer_name}</td>
                    <td style="padding:16px 24px;color:#475569;font-size:14px;">${row.dates}</td>
                    <td style="padding:16px 24px;color:#475569;">${row.total_price}</td>
                    <td style="padding:16px 24px;color:#ef4444;">${row.admin_commission}</td>
                    <td style="padding:16px 24px;color:#10b981;font-weight:600;">${row.host_payout}</td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="6" style="padding:24px;text-align:center;color:#64748b;">No payout history yet.</td></tr>`;
        }
    } catch (err) {
        document.getElementById('hhb-payouts-wrapper').innerHTML = `<div style="padding:20px;background:#fee2e2;color:#991b1b;border-radius:8px;">Failed to load payouts.</div>`;
    }
});
</script>
