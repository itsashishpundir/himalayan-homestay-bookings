<?php
/**
 * Host Dashboard — Calendar View (Decoupled REST API Client)
 *
 * @package Himalayan\Homestay
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>

<style>
.hhb-calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.hhb-cal-nav { display: flex; align-items: center; gap: 16px; }
.hhb-cal-btn { background: #fff; border: 1px solid #e2e8f0; color: #334155; height: 36px; padding: 0 12px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; text-decoration: none; font-size: 14px; font-weight: 500; }
.hhb-calendar-grid { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
.hhb-cal-row-header { display: grid; grid-template-columns: repeat(7, 1fr); background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
.hhb-cal-col-header { padding: 12px; text-align: center; font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
.hhb-cal-cell { min-height: 120px; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; padding: 8px; position: relative; background: #fff; }
.hhb-cal-cell:nth-child(7n) { border-right: none; }
.hhb-cal-cell.empty { background: #f8fafc; opacity: 0.5; }
.hhb-cal-date { font-size: 14px; font-weight: 600; color: #334155; margin-bottom: 8px; }
.hhb-cal-today { background: #2563eb; color: #fff; width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; }
.hhb-cal-event { background: #eff6ff; border-left: 3px solid #3b82f6; color: #1d4ed8; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer; }
.hhb-cal-event.blocked { background: #f1f5f9; border-color: #64748b; color: #475569; }
.hhb-loading-shimmer { animation: pk-shimmer 1.5s infinite linear; background: linear-gradient(to right, #f1f5f9 4%, #e2e8f0 25%, #f1f5f9 36%); background-size: 1000px 100%; height: 20px; border-radius: 4px; }
@keyframes pk-shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
</style>

<div id="hhb-calendar-wrapper">
    <div class="hhb-calendar-header" id="hhb-cal-header" style="opacity: 0;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <select id="hhb-cal-prop-select" style="padding: 8px 32px 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600; color: #0f172a; background: #fff;"></select>
        </div>
        <div class="hhb-cal-nav">
            <h2 id="hhb-cal-month-name" style="margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; width: 160px; text-align: center;">Loading...</h2>
            <button class="hhb-cal-btn" id="btn-prev-month"><span class="material-symbols-outlined" style="font-size: 18px;">chevron_left</span></button>
            <button class="hhb-cal-btn" id="btn-next-month"><span class="material-symbols-outlined" style="font-size: 18px;">chevron_right</span></button>
        </div>
    </div>

    <div class="hhb-calendar-grid" id="hhb-cal-grid-cnt">
        <div class="hhb-cal-row-header">
            <div class="hhb-cal-col-header">Sun</div>
            <div class="hhb-cal-col-header">Mon</div>
            <div class="hhb-cal-col-header">Tue</div>
            <div class="hhb-cal-col-header">Wed</div>
            <div class="hhb-cal-col-header">Thu</div>
            <div class="hhb-cal-col-header">Fri</div>
            <div class="hhb-cal-col-header">Sat</div>
        </div>
        <div id="hhb-cal-body" style="min-height: 480px; padding: 24px;">
            <div class="hhb-loading-shimmer" style="width: 100%; height: 400px; border-radius: 8px;"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    loadCalendar(params.get('property_id') || '', params.get('month') || '', params.get('year') || '');
});

async function loadCalendar(propId, month, year) {
    const wrapper = document.getElementById('hhb-cal-body');
    const header  = document.getElementById('hhb-cal-header');
    const nonce   = '<?php echo wp_create_nonce("wp_rest"); ?>';

    wrapper.innerHTML = `<div style="padding:24px;"><div class="hhb-loading-shimmer" style="width:100%;height:400px;border-radius:8px;"></div></div>`;
    header.style.opacity = '0.5';

    let url = `/wp-json/hhb/v1/host/calendar?_t=${Date.now()}`;
    if (propId) url += `&property_id=${propId}`;
    if (month) url += `&month=${month}`;
    if (year) url += `&year=${year}`;

    try {
        const res = await fetch(url, { headers: { 'X-WP-Nonce': nonce }, credentials: 'same-origin' });
        const data = await res.json();

        if ( ! data.has_properties ) {
            document.getElementById('hhb-calendar-wrapper').innerHTML = `
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:40px; text-align:center;">
                    <span class="material-symbols-outlined" style="font-size:48px; color:#cbd5e1;">event_busy</span>
                    <h2 style="margin:8px 0; color:#1e293b; font-size:20px;">No Active Properties</h2>
                </div>
            `;
            return;
        }

        document.getElementById('hhb-cal-month-name').innerText = `${data.nav.month_name} ${data.nav.current_year}`;

        const sel = document.getElementById('hhb-cal-prop-select');
        sel.innerHTML = data.properties.map(p => `<option value="${p.id}" ${p.id == data.selected_property_id ? 'selected' : ''}>${p.title}</option>`).join('');
        sel.onchange = e => loadCalendar(e.target.value, data.nav.current_month, data.nav.current_year);
        document.getElementById('btn-prev-month').onclick = () => loadCalendar(data.selected_property_id, data.nav.prev_month, data.nav.prev_year);
        document.getElementById('btn-next-month').onclick = () => loadCalendar(data.selected_property_id, data.nav.next_month, data.nav.next_year);
        header.style.opacity = '1';

        let html = '<div style="display:flex; flex-wrap:wrap;">';
        for ( let i = 0; i < data.grid.first_day_of_week; i++ ) {
            html += '<div class="hhb-cal-cell empty" style="width:14.2857%;"></div>';
        }
        for ( let day = 1; day <= data.grid.days_in_month; day++ ) {
            const isToday = (day === data.grid.today);
            const dateHtml = isToday ? `<span class="hhb-cal-today">${day}</span>` : `<span>${day}</span>`;
            let evHtml = '';
            if ( data.events && data.events[day] ) {
                evHtml = data.events[day].map(ev => `<div class="hhb-cal-event ${ev.type === 'blocked' ? 'blocked' : ''}" title="${ev.customer_name}">${ev.customer_name}</div>`).join('');
            }
            html += `<div class="hhb-cal-cell" style="width:14.2857%;"><div class="hhb-cal-date">${dateHtml}</div>${evHtml}</div>`;
        }
        html += '</div>';
        wrapper.innerHTML = html;
        wrapper.style.padding = '0';

    } catch (err) {
        wrapper.innerHTML = `<div style="padding:24px; color:#991b1b; background:#fee2e2;">Error loading calendar data.</div>`;
    }
}
</script>
