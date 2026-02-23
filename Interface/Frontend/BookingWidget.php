<?php
/**
 * Frontend Booking Widget
 *
 * Renders the booking form on single homestay pages as a shortcode.
 * Features AJAX availability checking, detailed price breakdown with
 * extra services, and deposit/balance display.
 *
 * @package Himalayan\Homestay\Interface\Frontend
 */

namespace Himalayan\Homestay\Interface\Frontend;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class BookingWidget {

    public static function init(): void {
        add_shortcode( 'hhb_booking_form', [ __CLASS__, 'render_widget' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    public static function enqueue_assets(): void {
        wp_enqueue_style( 'flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css' );
        wp_enqueue_script( 'flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true );
    }

    public static function render_widget( $atts ): string {
        $homestay_id = get_the_ID();
        $base_price  = get_post_meta( $homestay_id, 'base_price_per_night', true );
        $offer_price = get_post_meta( $homestay_id, 'offer_price_per_night', true );
        $currency    = get_post_meta( $homestay_id, 'currency', true ) ?: 'INR';
        $max_guests  = get_post_meta( $homestay_id, 'max_guests', true ) ?: 6;
        $min_nights  = get_post_meta( $homestay_id, 'hhb_min_nights', true ) ?: 1;
        $deposit_pct = get_post_meta( $homestay_id, 'hhb_deposit_percent', true ) ?: 0;

        // Fetch extra services for this homestay.
        global $wpdb;
        $services_table = $wpdb->prefix . 'himalayan_extra_services';
        $services = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$services_table} WHERE (homestay_id = %d OR homestay_id = 0) AND is_active = 1 ORDER BY sort_order",
            $homestay_id
        ) );

        ob_start();
        ?>
        <div class="hhb-booking-widget"
             data-id="<?php echo esc_attr( $homestay_id ); ?>"
             data-price="<?php echo esc_attr( $base_price ); ?>"
             data-currency="<?php echo esc_attr( $currency ); ?>"
             data-min-nights="<?php echo esc_attr( $min_nights ); ?>">

            <!-- Price Header -->
            <div class="hhb-widget-header">
                <h3>
                    <?php if ( $offer_price && $offer_price < $base_price ) : ?>
                        <span class="hhb-price-old"><?php echo esc_html( $currency . ' ' . number_format( $base_price ) ); ?></span>
                        <span class="hhb-price-current"><?php echo esc_html( $currency . ' ' . number_format( $offer_price ) ); ?></span>
                    <?php else : ?>
                        <span class="hhb-price-current"><?php echo esc_html( $currency . ' ' . number_format( $base_price ) ); ?></span>
                    <?php endif; ?>
                    <span class="hhb-price-unit">/ Night</span>
                </h3>
                <?php if ( $min_nights > 1 ) : ?>
                    <p class="hhb-min-stay"><?php printf( esc_html__( 'Minimum %d nights', 'himalayan-homestay-bookings' ), $min_nights ); ?></p>
                <?php endif; ?>
            </div>

            <form id="hhb-booking-form">
                <!-- Date Fields -->
                <div class="hhb-dates">
                    <div class="hhb-field">
                        <label><?php esc_html_e( 'Check-in', 'himalayan-homestay-bookings' ); ?></label>
                        <input type="text" id="hhb-check-in" required placeholder="<?php esc_attr_e( 'Select date', 'himalayan-homestay-bookings' ); ?>">
                    </div>
                    <div class="hhb-field">
                        <label><?php esc_html_e( 'Check-out', 'himalayan-homestay-bookings' ); ?></label>
                        <input type="text" id="hhb-check-out" required placeholder="<?php esc_attr_e( 'Select date', 'himalayan-homestay-bookings' ); ?>">
                    </div>
                </div>

                <!-- Guests -->
                <div class="hhb-field">
                    <label><?php esc_html_e( 'Guests', 'himalayan-homestay-bookings' ); ?></label>
                    <select id="hhb-guests">
                        <?php for ( $i = 1; $i <= $max_guests; $i++ ) : ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?> <?php echo $i === 1 ? 'Guest' : 'Guests'; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Extra Services -->
                <?php if ( ! empty( $services ) ) : ?>
                <div class="hhb-extras" style="margin-bottom:15px">
                    <label class="hhb-section-label"><?php esc_html_e( 'Extras & Add-ons', 'himalayan-homestay-bookings' ); ?></label>
                    <?php foreach ( $services as $svc ) :
                        $type_label = ucwords( str_replace( '_', ' ', $svc->price_type ) );
                    ?>
                        <label class="hhb-extra-item">
                            <input type="checkbox" class="hhb-service-cb" value="<?php echo esc_attr( $svc->id ); ?>">
                            <span class="hhb-extra-name"><?php echo esc_html( $svc->service_name ); ?></span>
                            <span class="hhb-extra-price"><?php echo esc_html( $currency . ' ' . number_format( $svc->price, 2 ) ); ?> (<?php echo esc_html( $type_label ); ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Customer Details (shown after availability check) -->
                <div class="hhb-customer-details" style="display:none">
                    <div class="hhb-field">
                        <label><?php esc_html_e( 'Full Name', 'himalayan-homestay-bookings' ); ?></label>
                        <input type="text" id="hhb-name" placeholder="John Doe">
                    </div>
                    <div class="hhb-field">
                        <label><?php esc_html_e( 'Email Address', 'himalayan-homestay-bookings' ); ?></label>
                        <input type="email" id="hhb-email" placeholder="john@example.com">
                    </div>
                    <div class="hhb-field">
                        <label><?php esc_html_e( 'Phone Number', 'himalayan-homestay-bookings' ); ?></label>
                        <input type="text" id="hhb-phone" placeholder="+91 98765 43210">
                    </div>
                    <div class="hhb-field">
                        <label><?php esc_html_e( 'Special Requests', 'himalayan-homestay-bookings' ); ?></label>
                        <textarea id="hhb-notes" rows="2" placeholder="<?php esc_attr_e( 'Any special requests or notes...', 'himalayan-homestay-bookings' ); ?>"></textarea>
                    </div>
                </div>

                <!-- Detailed Price Breakdown -->
                <div class="hhb-price-breakdown" style="display:none">
                    <div class="hhb-line" id="hhb-line-nightly">
                        <span class="hhb-line-label"></span>
                        <span class="hhb-line-value"></span>
                    </div>
                    <div class="hhb-line" id="hhb-line-extra-guest" style="display:none">
                        <span class="hhb-line-label"><?php esc_html_e( 'Extra Guest Fee', 'himalayan-homestay-bookings' ); ?></span>
                        <span class="hhb-line-value"></span>
                    </div>
                    <div class="hhb-line" id="hhb-line-services" style="display:none">
                        <span class="hhb-line-label"><?php esc_html_e( 'Add-on Services', 'himalayan-homestay-bookings' ); ?></span>
                        <span class="hhb-line-value"></span>
                    </div>
                    <div class="hhb-line hhb-line-total">
                        <span class="hhb-line-label"><?php esc_html_e( 'Total', 'himalayan-homestay-bookings' ); ?></span>
                        <span class="hhb-line-value" id="hhb-total-price"></span>
                    </div>
                    <?php if ( $deposit_pct > 0 && $deposit_pct < 100 ) : ?>
                    <div class="hhb-line hhb-deposit-line">
                        <span class="hhb-line-label"><?php printf( esc_html__( 'Deposit (%d%%)', 'himalayan-homestay-bookings' ), $deposit_pct ); ?></span>
                        <span class="hhb-line-value" id="hhb-deposit-amount"></span>
                    </div>
                    <div class="hhb-line">
                        <span class="hhb-line-label"><?php esc_html_e( 'Balance Due at Check-in', 'himalayan-homestay-bookings' ); ?></span>
                        <span class="hhb-line-value" id="hhb-balance-due"></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="hhb-messages" id="hhb-messages"></div>

                <button type="button" id="hhb-check-btn" class="hhb-btn"><?php esc_html_e( 'Check Availability', 'himalayan-homestay-bookings' ); ?></button>
                <button type="submit" id="hhb-book-btn" class="hhb-btn" style="display:none"><?php esc_html_e( 'Request to Book', 'himalayan-homestay-bookings' ); ?></button>
            </form>
        </div>

        <style>
            .hhb-booking-widget { background: transparent !important; padding: 0 !important; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .hhb-widget-header h3 { margin: 0 0 8px; font-size: 24px; font-weight: 700; color: inherit; }
            .hhb-price-old { text-decoration: line-through; color: #999; font-size: 0.7em; font-weight: 400; }
            .hhb-price-current { color: inherit; }
            .hhb-price-unit { font-size: 14px; font-weight: 400; color: #666; }
            .hhb-min-stay { margin: 0 0 16px; font-size: 12px; color: #888; }
            .hhb-dates { display: flex; gap: 12px; margin-bottom: 12px; }
            .hhb-field { flex: 1; margin-bottom: 12px; }
            .hhb-field label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; color: inherit; opacity: 0.7; }
            .hhb-field input, .hhb-field select, .hhb-field textarea { width: 100%; padding: 10px 12px; border: 1px solid rgba(0,0,0,0.15); border-radius: 8px; box-sizing: border-box; background: rgba(255,255,255,0.9); color: #333; font-size: 14px; transition: border-color 0.2s; }
            .hhb-field input:focus, .hhb-field select:focus, .hhb-field textarea:focus { outline: none; border-color: #f45c25; background: #fff; box-shadow: 0 0 0 3px rgba(244,92,37,0.1); }

            /* Extras */
            .hhb-section-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; color: inherit; opacity: 0.7; }
            .hhb-extra-item { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border: 1px solid rgba(0,0,0,0.08); border-radius: 6px; margin-bottom: 4px; cursor: pointer; font-size: 13px; transition: all 0.2s; }
            .hhb-extra-item:hover { border-color: #f45c25; background: rgba(244,92,37,0.03); }
            .hhb-extra-name { flex: 1; font-weight: 500; }
            .hhb-extra-price { color: #888; font-size: 12px; }

            /* Price Breakdown */
            .hhb-price-breakdown { margin: 16px 0; padding: 16px; background: rgba(0,0,0,0.03); border-radius: 10px; }
            .hhb-line { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; color: inherit; }
            .hhb-line-label { color: #666; }
            .hhb-line-value { font-weight: 600; }
            .hhb-line-total { border-top: 2px solid rgba(0,0,0,0.1); padding-top: 10px; margin-top: 6px; font-size: 16px; }
            .hhb-line-total .hhb-line-value { color: #f45c25; font-size: 18px; }
            .hhb-deposit-line { color: #2e7d32; }
            .hhb-deposit-line .hhb-line-value { color: #2e7d32; }

            /* Customer details */
            .hhb-customer-details { margin-top: 16px; border-top: 1px solid rgba(0,0,0,0.08); padding-top: 16px; }

            /* Buttons */
            .hhb-btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #f45c25, #e04010); color: #fff; border: none; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 20px rgba(244,92,37,0.3); letter-spacing: 0.3px; }
            .hhb-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(244,92,37,0.4); }
            .hhb-btn:active { transform: translateY(0); }
            .hhb-btn:disabled { background: #ccc; cursor: not-allowed; transform: none; box-shadow: none; color: #666; }

            /* Messages */
            .hhb-messages { font-size: 13px; margin-bottom: 12px; padding: 10px 14px; border-radius: 8px; display: none; }
            .hhb-messages.error { background: #fff0f0; color: #cc0000; border: 1px solid #ffcccc; display: block; }
            .hhb-messages.success { background: #f0fff0; color: #006600; border: 1px solid #ccffcc; display: block; }
        </style>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            if (typeof flatpickr === "undefined") return;

            const widget = document.querySelector(".hhb-booking-widget");
            if (!widget) return;

            const homestayId   = widget.dataset.id;
            const minNights    = parseInt(widget.dataset.minNights) || 1;
            const checkInEl    = document.getElementById("hhb-check-in");
            const checkOutEl   = document.getElementById("hhb-check-out");
            const checkBtn     = document.getElementById("hhb-check-btn");
            const bookBtn      = document.getElementById("hhb-book-btn");
            const customerDiv  = document.querySelector(".hhb-customer-details");
            const breakdownDiv = document.querySelector(".hhb-price-breakdown");
            const messages     = document.getElementById("hhb-messages");

            const fpIn = flatpickr(checkInEl, {
                minDate: "today",
                dateFormat: "Y-m-d",
                onChange: function(sel) {
                    if (sel.length > 0) {
                        const minDate = new Date(sel[0].getTime() + (minNights * 86400000));
                        fpOut.set("minDate", minDate);
                    }
                    resetForm();
                }
            });
            const fpOut = flatpickr(checkOutEl, {
                minDate: new Date(new Date().getTime() + 86400000),
                dateFormat: "Y-m-d",
                onChange: resetForm
            });

            function showMsg(msg, type) {
                messages.textContent = msg;
                messages.className = "hhb-messages " + type;
            }

            function resetForm() {
                bookBtn.style.display = "none";
                checkBtn.style.display = "block";
                customerDiv.style.display = "none";
                breakdownDiv.style.display = "none";
                messages.className = "hhb-messages";
            }

            function getSelectedServiceIds() {
                return Array.from(document.querySelectorAll(".hhb-service-cb:checked")).map(cb => cb.value);
            }

            checkBtn.addEventListener("click", async () => {
                const ci = checkInEl.value, co = checkOutEl.value;
                if (!ci || !co) return showMsg("Please select your dates.", "error");

                checkBtn.textContent = "Checking...";
                checkBtn.disabled = true;

                try {
                    // 1. Check availability
                    const res = await fetch("<?php echo esc_url( rest_url( 'himalayan/v1/check-availability' ) ); ?>?homestay_id=" + homestayId + "&check_in=" + ci + "&check_out=" + co);
                    const data = await res.json();

                    if (!res.ok || !data.available) {
                        showMsg(data.message || "Dates are not available.", "error");
                        checkBtn.textContent = "Check Availability"; checkBtn.disabled = false;
                        return;
                    }

                    // 2. Calculate price with services
                    const guests     = document.getElementById("hhb-guests").value;
                    const serviceIds = getSelectedServiceIds();
                    let priceUrl     = "<?php echo esc_url( rest_url( 'himalayan/v1/calculate-price' ) ); ?>?homestay_id=" + homestayId + "&check_in=" + ci + "&check_out=" + co + "&guests=" + guests;
                    serviceIds.forEach(id => priceUrl += "&services[]=" + id);

                    const priceRes  = await fetch(priceUrl);
                    const priceData = await priceRes.json();

                    if (priceRes.ok) {
                        // Populate breakdown
                        const c = priceData.currency || "INR";
                        document.querySelector("#hhb-line-nightly .hhb-line-label").textContent = c + " " + priceData.base_per_night + " × " + priceData.nights + " nights";
                        document.querySelector("#hhb-line-nightly .hhb-line-value").textContent = c + " " + priceData.nightly_total;

                        const egLine = document.getElementById("hhb-line-extra-guest");
                        if (priceData.extra_guest_charge > 0) {
                            egLine.style.display = "flex";
                            egLine.querySelector(".hhb-line-value").textContent = c + " " + priceData.extra_guest_charge;
                        } else { egLine.style.display = "none"; }

                        const svcLine = document.getElementById("hhb-line-services");
                        if (priceData.services_total > 0) {
                            svcLine.style.display = "flex";
                            svcLine.querySelector(".hhb-line-value").textContent = c + " " + priceData.services_total;
                        } else { svcLine.style.display = "none"; }

                        document.getElementById("hhb-total-price").textContent = c + " " + priceData.grand_total;

                        const depositEl = document.getElementById("hhb-deposit-amount");
                        const balanceEl = document.getElementById("hhb-balance-due");
                        if (depositEl) depositEl.textContent = c + " " + priceData.deposit_amount;
                        if (balanceEl) balanceEl.textContent = c + " " + priceData.balance_due;

                        breakdownDiv.style.display = "block";
                        customerDiv.style.display = "block";
                        checkBtn.style.display = "none";
                        bookBtn.style.display = "block";
                        showMsg("Dates are available! Complete your details below.", "success");
                    }
                } catch (e) {
                    showMsg("Network error. Please try again.", "error");
                }
                checkBtn.textContent = "Check Availability"; checkBtn.disabled = false;
            });

            document.getElementById("hhb-booking-form").addEventListener("submit", async (e) => {
                e.preventDefault();
                bookBtn.textContent = "Processing..."; bookBtn.disabled = true;

                const payload = {
                    homestay_id: homestayId,
                    check_in: checkInEl.value,
                    check_out: checkOutEl.value,
                    guests: document.getElementById("hhb-guests").value,
                    customer_name: document.getElementById("hhb-name").value,
                    customer_email: document.getElementById("hhb-email").value,
                    customer_phone: document.getElementById("hhb-phone").value,
                    notes: document.getElementById("hhb-notes").value,
                    services: getSelectedServiceIds()
                };

                if (!payload.customer_name || !payload.customer_email) {
                    showMsg("Name and Email are required.", "error");
                    bookBtn.textContent = "Request to Book"; bookBtn.disabled = false;
                    return;
                }

                try {
                    const res = await fetch("<?php echo esc_url( rest_url( 'himalayan/v1/create-booking' ) ); ?>", {
                        method: "POST",
                        headers: {"Content-Type": "application/json"},
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json();

                    if (res.ok) {
                        showMsg("🎉 Booking request sent successfully! We'll contact you soon.", "success");
                        bookBtn.style.display = "none";
                        customerDiv.style.display = "none";
                    } else {
                        showMsg(data.message || "Booking failed.", "error");
                        bookBtn.textContent = "Request to Book"; bookBtn.disabled = false;
                    }
                } catch (e) {
                    showMsg("Network error. Please try again.", "error");
                    bookBtn.textContent = "Request to Book"; bookBtn.disabled = false;
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
