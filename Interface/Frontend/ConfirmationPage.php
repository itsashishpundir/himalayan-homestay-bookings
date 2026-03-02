<?php
/**
 * Booking Confirmation Frontend Page
 *
 * Intercepts requests with ?hhb_confirmation=<booking_id> and renders
 * a dedicated Thank You / Booking Confirmation screen.
 * Status-aware: shows different UI for confirmed (paid), approved, or pending.
 *
 * @package Himalayan\Homestay\Interface\Frontend
 */

namespace Himalayan\Homestay\Interface\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ConfirmationPage {

	public static function init(): void {
		add_filter( 'template_include', [ __CLASS__, 'intercept_confirmation_template' ], 99 );
		add_action( 'template_redirect', [ __CLASS__, 'handle_redirects' ] );
	}

	public static function handle_redirects(): void {
		if ( isset( $_GET['hhb_confirmation'] ) && ! is_user_logged_in() && empty( $_GET['hash'] ) ) {
			// Optional: protect access if needed, but since it's just a confirmation, it's okay.
		}
	}

	public static function intercept_confirmation_template( $template ) {
		if ( isset( $_GET['hhb_confirmation'] ) ) {
			$booking_id = intval( $_GET['hhb_confirmation'] );
			if ( $booking_id > 0 ) {
				// If the theme has a custom confirmation template, use it.
				$custom_template = locate_template( [ 'hhb-confirmation.php' ] );
				if ( $custom_template ) {
					return $custom_template;
				}

				// Otherwise, render our built-in one and stop WP.
				self::render_standalone_page( $booking_id );
				exit;
			}
		}
		return $template;
	}

	private static function render_standalone_page( int $booking_id ): void {
		global $wpdb;
		$table   = $wpdb->prefix . 'himalayan_bookings';
		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $booking_id ), ARRAY_A );

		if ( ! $booking ) {
			wp_die( __( 'Booking not found.', 'himalayan-homestay-bookings' ) );
		}

		$homestay    = get_post( $booking['homestay_id'] );
		$hs_title    = $homestay ? $homestay->post_title : 'Homestay';
		$hs_link     = $homestay ? get_permalink( $homestay ) : home_url();
		$home_url    = home_url();
		$status      = $booking['status'] ?? 'pending';
		$admin_email = get_option( 'hhb_smtp_email' ) ?: get_option( 'admin_email' );

		$fmt       = get_option( 'date_format' );
		$check_in  = date_i18n( $fmt, strtotime( $booking['check_in'] ) );
		$check_out = date_i18n( $fmt, strtotime( $booking['check_out'] ) );
		$nights    = (int) ( ( strtotime( $booking['check_out'] ) - strtotime( $booking['check_in'] ) ) / DAY_IN_SECONDS );

		// ── Status-driven UI config ───────────────────────────────────────────
		if ( $status === 'confirmed' ) {
			$header_bg   = 'linear-gradient(135deg,#e85e30,#b34520)';
			$icon_path   = 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z';
			$heading     = __( 'Booking Confirmed & Paid!', 'himalayan-homestay-bookings' );
			$sub         = __( 'Your payment was received. Your stay is fully confirmed.', 'himalayan-homestay-bookings' );
			$body_msg    = sprintf(
				__( 'Thank you, <strong>%s</strong>! Your booking for <strong>%s</strong> has been <strong style="color:#e85e30;">confirmed and payment received</strong>. A confirmation email with your invoice has been sent to <strong>%s</strong>.', 'himalayan-homestay-bookings' ),
				esc_html( $booking['customer_name'] ),
				esc_html( $hs_title ),
				esc_html( $booking['customer_email'] )
			);
			$badge_color = \Himalayan\Homestay\Domain\Booking\BookingStatus::get_color( $status );
			$badge_bg    = $badge_color . '20';
			$badge_text  = '✅ ' . __( 'Payment Confirmed', 'himalayan-homestay-bookings' );
			$status_val  = '<span style="color:' . $badge_color . ';font-weight:700;">✅ ' . __( 'Confirmed & Paid', 'himalayan-homestay-bookings' ) . '</span>';
			$extra_row   = '<div class="hhb-conf-row"><span class="hhb-conf-label">' . __( 'Transaction ID', 'himalayan-homestay-bookings' ) . '</span><span class="hhb-conf-val" style="font-size:11px;word-break:break-all;">' . esc_html( $booking['transaction_id'] ?: '—' ) . '</span></div>';
			$btn_text    = __( '← View Property', 'himalayan-homestay-bookings' );
			$btn_href    = esc_url( $hs_link );

		} elseif ( $status === 'approved' ) {
			$header_bg   = 'linear-gradient(135deg,#1565c0,#0d47a1)';
			$icon_path   = 'M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z';
			$heading     = __( 'Booking Approved — Pay Now!', 'himalayan-homestay-bookings' );
			$sub         = __( 'Your host approved your stay. Complete payment within 60 minutes to hold your dates.', 'himalayan-homestay-bookings' );
			$body_msg    = sprintf(
				__( 'Great news, <strong>%s</strong>! Your booking at <strong>%s</strong> has been approved. Please check your email at <strong>%s</strong> for the secure payment link. <br><br><strong style="color:#e65100;">⏱ Please pay within 60 minutes — unpaid bookings are auto-released.</strong>', 'himalayan-homestay-bookings' ),
				esc_html( $booking['customer_name'] ),
				esc_html( $hs_title ),
				esc_html( $booking['customer_email'] )
			);
			$badge_color = \Himalayan\Homestay\Domain\Booking\BookingStatus::get_color( $status );
			$badge_bg    = $badge_color . '20';
			$badge_text  = '⏳ ' . __( 'Payment Pending', 'himalayan-homestay-bookings' );
			$status_val  = '<span style="color:' . $badge_color . ';font-weight:700;">✅ ' . __( 'Approved — Awaiting Payment', 'himalayan-homestay-bookings' ) . '</span>';
			$extra_row   = '';
			$btn_text    = __( '← Return to Homepage', 'himalayan-homestay-bookings' );
			$btn_href    = esc_url( $home_url );

		} elseif ( $status === 'payment_expired' ) {
			$header_bg   = 'linear-gradient(135deg,#b71c1c,#7f0000)';
			$icon_path   = 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z';
			$heading     = __( 'Payment Window Expired', 'himalayan-homestay-bookings' );
			$sub         = __( 'The 60-minute payment window has closed and dates have been released.', 'himalayan-homestay-bookings' );
			$body_msg    = sprintf(
				__( 'Hi <strong>%s</strong>, unfortunately your payment window for the booking at <strong>%s</strong> expired before payment was completed. The dates are now available again.<br><br>Please <a href="%s">contact us</a> if you wish to rebook.', 'himalayan-homestay-bookings' ),
				esc_html( $booking['customer_name'] ),
				esc_html( $hs_title ),
				esc_url( site_url( '/contact/' ) )
			);
			$badge_color = \Himalayan\Homestay\Domain\Booking\BookingStatus::get_color( $status );
			$badge_bg    = $badge_color . '20';
			$badge_text  = '⚠️ ' . __( 'Payment Expired', 'himalayan-homestay-bookings' );
			$status_val  = '<span style="color:' . $badge_color . ';font-weight:700;">⚠️ ' . __( 'Payment Expired', 'himalayan-homestay-bookings' ) . '</span>';
			$extra_row   = '';
			$btn_text    = __( '← Return to Homepage', 'himalayan-homestay-bookings' );
			$btn_href    = esc_url( $home_url );

		} else {
			// Pending status - show payment buttons
			$amount_due      = (float) ( $booking['deposit_amount'] > 0 ? $booking['deposit_amount'] : $booking['total_price'] );
			$amount_in_paise = round( $amount_due * 100 );
            $settings        = get_option( 'hhb_payment_settings', [] );

			$header_bg   = 'linear-gradient(135deg,#e65100,#ff8f00)';
			$icon_path   = 'M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z';
			$heading     = __( 'Complete Your Payment', 'himalayan-homestay-bookings' );
			$sub         = __( 'Secure your dates by completing the payment within 30 minutes.', 'himalayan-homestay-bookings' );
			
			$body_msg    = sprintf(
				__( 'Almost there, <strong>%s</strong>! Your dates for <strong>%s</strong> are temporarily held.<br><br><strong style="color:#e65100;">⏱ Please pay within 30 minutes — unpaid bookings are auto-released.</strong>', 'himalayan-homestay-bookings' ),
				esc_html( $booking['customer_name'] ),
				esc_html( $hs_title )
			);

            $badge_color = \Himalayan\Homestay\Infrastructure\Database\BookingManager::get_status_color( $status );
			$badge_bg    = $badge_color . '20';
			$badge_text  = '⏳ ' . __( 'Awaiting Payment', 'himalayan-homestay-bookings' );
			$status_val  = '<span style="color:' . $badge_color . ';font-weight:700;">⏳ ' . __( 'Pending', 'himalayan-homestay-bookings' ) . '</span>';
			
			$buttons_html = '<div class="hhb-payment-actions" style="display:flex;flex-direction:column;gap:12px;margin-top:20px;">';
            $buttons_html .= '<div id="hhb-payment-error" style="color:#d32f2f;font-weight:600;font-size:14px;display:none;padding:12px;background:#ffebee;border-radius:8px;margin-bottom:12px;text-align:center;"></div>';

            $razorpay_order_id = '';
            $razorpay_key      = '';
            if ( ! empty( $settings['razorpay_enabled'] ) && 'yes' === $settings['razorpay_enabled'] ) {
                $gateway = new \Himalayan\Homestay\Infrastructure\Payments\RazorpayGateway();
                if ( $gateway->is_active() ) {
                    $booking_obj = (object) $booking; // create_order expects object
                    $order_data = $gateway->create_order( $booking_obj, $amount_in_paise );
                    if ( ! isset( $order_data['error'] ) ) {
                        $razorpay_order_id = $order_data['id'];
                        $razorpay_key      = $gateway->get_key_id();
                        $buttons_html .= '<button id="hhb-pay-razorpay" class="hhb-btn-pay" style="background:#0d47a1;color:#fff;border:none;padding:14px 24px;border-radius:8px;font-weight:700;cursor:pointer;width:100%;">Pay securely with Razorpay</button>';
                    } else {
                    }
                }
            }

            $buttons_html .= '</div>';
            
            // Script tag for Razorpay checkout integration
            $buttons_html .= '<script src="https://checkout.razorpay.com/v1/checkout.js"></script>';
            $buttons_html .= '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    const errBox = document.getElementById("hhb-payment-error");
                    let isPaymentComplete = false;
                    let hasDropped = false;

                    function showError(msg) {
                        errBox.style.display = "block";
                        errBox.textContent = msg;
                    }

                    function triggerDropBooking() {
                        if (isPaymentComplete || hasDropped) return;
                        hasDropped = true; // Prevent multiple calls
                        
                        // Use Beacon API for guaranteed delivery even if page unloads
                        const dropUrl = "' . esc_url( rest_url( 'himalayan/v1/drop-booking' ) ) . '";
                        const payload = JSON.stringify({ booking_id: ' . intval( $booking_id ) . ', token: "' . esc_js( $booking['payment_token'] ) . '" });
                        
                        if (navigator.sendBeacon) {
                            navigator.sendBeacon(dropUrl, new Blob([payload], {type: "application/json"}));
                        } else {
                            fetch(dropUrl, { method: "POST", headers: { "Content-Type": "application/json" }, body: payload, keepalive: true }).catch(()=>{});
                        }
                    }

                    // Attempt to drop if user leaves page without paying
                    window.addEventListener("beforeunload", function(e) {
                        if (!isPaymentComplete && !hasDropped) {
                            triggerDropBooking();
                        }
                    });

                    // Razorpay Logic
                    const rzpBtn = document.getElementById("hhb-pay-razorpay");
                    if (rzpBtn && "' . esc_js($razorpay_order_id) . '") {
                        var rzpOptions = {
                            "key": "' . esc_js($razorpay_key) . '",
                            "amount": "' . esc_js($amount_in_paise) . '",
                            "currency": "INR",
                            "name": "' . esc_js( get_bloginfo('name') ) . '",
                            "description": "Booking #' . esc_js($booking_id) . '",
                            "order_id": "' . esc_js($razorpay_order_id) . '",
                            "handler": function (response){
                                isPaymentComplete = true;
                                rzpBtn.innerText = "Verifying Payment...";
                                fetch("' . esc_url( rest_url( 'himalayan/v1/razorpay-verify' ) ) . '", {
                                    method: "POST", headers: { "Content-Type": "application/json" },
                                    body: JSON.stringify({
                                        razorpay_payment_id: response.razorpay_payment_id,
                                        razorpay_order_id: response.razorpay_order_id,
                                        razorpay_signature: response.razorpay_signature,
                                        booking_id: ' . intval( $booking_id ) . ',
                                        token: "' . esc_js( $booking['payment_token'] ) . '"
                                    })
                                }).then(res => res.json()).then(data => {
                                    if(data.success) { window.location.reload(); }
                                    else { showError(data.message || "Verification failed."); isPaymentComplete = false; rzpBtn.innerText = "Pay securely with Razorpay"; }
                                }).catch(err => {
                                    showError("Network error during verification."); isPaymentComplete = false; rzpBtn.innerText = "Pay securely with Razorpay";
                                });
                            },
                            "prefill": {
                                "name": "' . esc_js( $booking['customer_name'] ) . '",
                                "email": "' . esc_js( $booking['customer_email'] ) . '",
                                "contact": "' . esc_js( $booking['customer_phone'] ) . '"
                            },
                            "theme": { "color": "#e85e30" },
                            "modal": {
                                "ondismiss": function() {
                                    triggerDropBooking();
                                    setTimeout(() => window.location.reload(), 500);
                                }
                            }
                        };
                        var rzp = new window.Razorpay(rzpOptions);
                        rzp.on("payment.failed", function (response){
                            triggerDropBooking();
                            showError("Payment Failed: " + response.error.description);
                            setTimeout(() => window.location.reload(), 2000);
                        });
                        rzpBtn.onclick = function(e){ e.preventDefault(); rzp.open(); };
                    }
                });
            </script>';

			$extra_row   = $buttons_html;
			$btn_text    = ''; // Remove Return to Home button since they need to pay
			$btn_href    = '';
		}
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php esc_html_e( 'Booking Confirmation', 'himalayan-homestay-bookings' ); ?> - <?php bloginfo( 'name' ); ?></title>
			<style>
				* { box-sizing: border-box; }
				body {
					margin: 0; padding: 20px;
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
					background: #f7f9fb; color: #333;
					display: flex; justify-content: center; align-items: flex-start;
					min-height: 100vh;
				}
				.hhb-conf-wrap { width: 100%; max-width: 640px; margin: 20px auto; }
				.hhb-conf-container {
					background: #fff; border-radius: 16px;
					box-shadow: 0 10px 40px rgba(0,0,0,0.09);
					overflow: hidden; text-align: center;
				}
				.hhb-conf-header {
					background: <?php echo $header_bg; ?>;
					color: #fff; padding: 44px 24px 36px;
				}
				.hhb-conf-header svg { width: 60px; height: 60px; fill: rgba(255,255,255,0.92); margin: 0 auto 16px; display: block; }
				.hhb-conf-header h1 { margin: 0 0 8px; font-size: 26px; font-weight: 800; letter-spacing: -0.5px; }
				.hhb-conf-header p  { margin: 0; font-size: 15px; opacity: 0.88; }
				.hhb-conf-body { padding: 36px 28px; }
				.hhb-badge {
					display: inline-flex; align-items: center; gap: 8px;
					background: <?php echo $badge_bg; ?>;
					color: <?php echo $badge_color; ?>;
					border: 1.5px solid <?php echo $badge_color; ?>;
					border-radius: 30px; padding: 8px 20px;
					font-weight: 700; font-size: 14px; margin-bottom: 24px;
				}
				.hhb-conf-msg { font-size: 15px; color: #555; line-height: 1.65; margin: 0 0 24px; text-align: left; }
				.hhb-conf-details {
					background: #fdfdfd; border: 1px solid #eaeaea;
					border-radius: 12px; padding: 20px 24px;
					text-align: left; margin-bottom: 28px;
				}
				.hhb-conf-row {
					display: flex; justify-content: space-between; align-items: center;
					border-bottom: 1px solid #f0f0f0; padding: 11px 0;
					gap: 12px;
				}
				.hhb-conf-row:last-child { border-bottom: none; }
				.hhb-conf-label { color: #888; font-size: 13px; font-weight: 500; white-space: nowrap; }
				.hhb-conf-val   { font-weight: 600; font-size: 14px; color: #111; text-align: right; }
				.hhb-btn-home {
					display: inline-block; background: #111; color: #fff;
					text-decoration: none; padding: 14px 32px;
					border-radius: 30px; font-weight: 700; font-size: 15px;
					transition: all 0.3s; margin-bottom: 20px;
				}
				.hhb-btn-home:hover { background: #333; transform: translateY(-2px); }
				.hhb-conf-footer { font-size: 13px; color: #aaa; margin-top: 4px; }
				.hhb-conf-footer a { color: inherit; }
			</style>
		</head>
		<body>
			<div class="hhb-conf-wrap">
				<div class="hhb-conf-container">
					<div class="hhb-conf-header">
						<svg viewBox="0 0 24 24"><path d="<?php echo $icon_path; ?>"/></svg>
						<h1><?php echo esc_html( $heading ); ?></h1>
						<p><?php echo esc_html( $sub ); ?></p>
					</div>
					<div class="hhb-conf-body">
						<div class="hhb-badge"><?php echo $badge_text; ?></div>
						<p class="hhb-conf-msg"><?php echo wp_kses_post( $body_msg ); ?></p>

						<div class="hhb-conf-details">
							<div class="hhb-conf-row">
								<span class="hhb-conf-label"><?php esc_html_e( 'Booking Ref', 'himalayan-homestay-bookings' ); ?></span>
								<span class="hhb-conf-val">#<?php echo esc_html( $booking['id'] ); ?></span>
							</div>
							<div class="hhb-conf-row">
								<span class="hhb-conf-label"><?php esc_html_e( 'Property', 'himalayan-homestay-bookings' ); ?></span>
								<span class="hhb-conf-val"><?php echo esc_html( $hs_title ); ?></span>
							</div>
							<div class="hhb-conf-row">
								<span class="hhb-conf-label"><?php esc_html_e( 'Guest', 'himalayan-homestay-bookings' ); ?></span>
								<span class="hhb-conf-val"><?php echo esc_html( $booking['customer_name'] ); ?></span>
							</div>
							<div class="hhb-conf-row">
								<span class="hhb-conf-label"><?php esc_html_e( 'Guests', 'himalayan-homestay-bookings' ); ?></span>
								<span class="hhb-conf-val"><?php echo esc_html( $booking['guests'] ); ?></span>
							</div>
							<div class="hhb-conf-row">
								<span class="hhb-conf-label"><?php esc_html_e( 'Dates', 'himalayan-homestay-bookings' ); ?></span>
								<span class="hhb-conf-val">
									<?php echo esc_html( $check_in ); ?> &rarr; <?php echo esc_html( $check_out ); ?><br>
									<span style="font-size:11px;color:#aaa;font-weight:400;">
										<?php echo esc_html( sprintf( _n( '%d night', '%d nights', $nights, 'himalayan-homestay-bookings' ), $nights ) ); ?>
									</span>
								</span>
							</div>
							<div class="hhb-conf-row">
								<span class="hhb-conf-label"><?php esc_html_e( 'Total Amount', 'himalayan-homestay-bookings' ); ?></span>
								<span class="hhb-conf-val">&#8377; <?php echo esc_html( number_format( (float) $booking['total_price'] ) ); ?></span>
							</div>
							<div class="hhb-conf-row">
								<span class="hhb-conf-label"><?php esc_html_e( 'Status', 'himalayan-homestay-bookings' ); ?></span>
								<span class="hhb-conf-val"><?php echo $status_val; ?></span>
							</div>
							<?php echo $extra_row; ?>
						</div>

						<a href="<?php echo $btn_href; ?>" class="hhb-btn-home"><?php echo esc_html( $btn_text ); ?></a>
						<p class="hhb-conf-footer">
							<?php
							printf(
								__( 'Need help? Contact us at %s', 'himalayan-homestay-bookings' ),
								'<a href="mailto:' . esc_attr( $admin_email ) . '">' . esc_html( $admin_email ) . '</a>'
							);
							?>
						</p>
					</div>
				</div>
			</div>
			<?php do_action( 'hhb_booking_confirmation_page', $booking_id ); ?>
		</body>
		</html>
		<?php
	}
}
