<?php
/**
 * Promotional Popup Manager — Frontend
 *
 * Outputs popup HTML + CSS + JS via wp_footer.
 * Reads settings from PopupsPage options.
 * Popup 2 email field connects to the newsletter subscriber system.
 *
 * @package Himalayan\Homestay\Infrastructure\Frontend
 */

namespace Himalayan\Homestay\Interface\Frontend;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PopupManager {

    public static function init(): void {
        add_action( 'wp_footer', [ __CLASS__, 'output_popups' ], 99 );

        // Popup 2 email subscribes to the newsletter system
        add_action( 'wp_ajax_hhb_popup2_subscribe',        [ __CLASS__, 'ajax_popup2_subscribe' ] );
        add_action( 'wp_ajax_nopriv_hhb_popup2_subscribe', [ __CLASS__, 'ajax_popup2_subscribe' ] );
    }

    // -------------------------------------------------------------------------
    // AJAX: Popup 2 email subscribe (reuses newsletter system)
    // -------------------------------------------------------------------------
    public static function ajax_popup2_subscribe(): void {
        check_ajax_referer( 'hhb_newsletter', 'nonce' );

        $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
        if ( ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => 'Please enter a valid email address.' ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'hhb_newsletter_subscribers';

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, status FROM {$table} WHERE email = %s", $email
        ) );

        if ( $existing ) {
            if ( $existing->status === 'active' ) {
                wp_send_json_success( [ 'message' => "You're already subscribed!" ] );
            }
            $wpdb->update( $table,
                [ 'status' => 'active', 'unsubscribed_at' => null ],
                [ 'id' => $existing->id ],
                [ '%s', null ], [ '%d' ]
            );
            wp_send_json_success( [ 'message' => 'Welcome back! You are re-subscribed.' ] );
        }

        $token = wp_generate_password( 32, false );
        $wpdb->insert( $table, [
            'email'             => $email,
            'name'              => '',
            'status'            => 'active',
            'unsubscribe_token' => $token,
        ], [ '%s', '%s', '%s', '%s' ] );

        if ( $wpdb->last_error ) {
            wp_send_json_error( [ 'message' => 'Could not save. Please try again.' ] );
        }

        wp_send_json_success( [ 'message' => "You're subscribed! Your free guide is on its way." ] );
    }

    // -------------------------------------------------------------------------
    // Output popup HTML to wp_footer
    // -------------------------------------------------------------------------
    public static function output_popups(): void {
        $p1_on = (int) \Himalayan\Homestay\Interface\Admin\PopupsPage::get( 'p1_enabled' );
        $p2_on = (int) \Himalayan\Homestay\Interface\Admin\PopupsPage::get( 'p2_enabled' );

        if ( ! $p1_on && ! $p2_on ) return;

        $p1_should = $p1_on && self::should_show( 'p1' );
        $p2_should = $p2_on && self::should_show( 'p2' );

        if ( ! $p1_should && ! $p2_should ) return;

        self::output_styles();

        if ( $p1_should ) self::output_popup1();
        if ( $p2_should ) self::output_popup2();

        self::output_scripts( $p1_should, $p2_should );
    }

    // -------------------------------------------------------------------------
    // Check page visibility setting
    // -------------------------------------------------------------------------
    private static function should_show( string $prefix ): bool {
        $pages = \Himalayan\Homestay\Interface\Admin\PopupsPage::get( $prefix . '_pages' );
        switch ( $pages ) {
            case 'home':    return is_front_page();
            case 'archive': return is_post_type_archive( 'hhb_homestay' );
            case 'single':  return is_singular( 'hhb_homestay' );
            case 'blog':    return is_singular( 'post' );
            default:        return true; // 'all'
        }
    }

    // -------------------------------------------------------------------------
    // Popup 1 HTML — Special Offer
    // -------------------------------------------------------------------------
    private static function output_popup1(): void {
        $g = fn( $k ) => \Himalayan\Homestay\Interface\Admin\PopupsPage::get( $k );

        $image       = esc_url( $g( 'p1_image' ) );
        $badge       = esc_html( $g( 'p1_badge' ) );
        $headline    = esc_html( $g( 'p1_headline' ) );
        $subtext     = esc_html( $g( 'p1_subtext' ) );
        $coupon      = esc_html( $g( 'p1_coupon' ) );
        $btn_text    = esc_html( $g( 'p1_btn_text' ) );
        $btn_url     = esc_url( $g( 'p1_btn_url' ) ?: home_url( '/homestays/' ) );
        $dismiss     = esc_html( $g( 'p1_dismiss' ) );
        $fine_print  = esc_html( $g( 'p1_fine_print' ) );
        $cookie_days = (int) $g( 'p1_cookie_days' );
        $trigger     = $g( 'p1_trigger' );
        $delay       = (int) $g( 'p1_delay' );
        $scroll_pct  = (int) $g( 'p1_scroll_pct' );
        ?>
        <div id="hhb-popup1" class="hhb-popup-overlay" aria-modal="true" role="dialog" aria-label="Special Offer"
             data-cookie-days="<?php echo $cookie_days; ?>"
             data-trigger="<?php echo esc_attr( $trigger ); ?>"
             data-delay="<?php echo $delay; ?>"
             data-scroll="<?php echo $scroll_pct; ?>">
            <div class="hhb-popup1-card">

                <!-- Left: Image -->
                <div class="hhb-popup1-img" style="<?php echo $image ? "background-image:url('{$image}');" : "background:linear-gradient(135deg,#1a1a2e 0%,#e85e30 100%);"; ?>">
                    <div class="hhb-popup1-img-overlay"></div>
                    <div class="hhb-popup1-img-text">
                        <span style="font-size:48px;">🏔️</span>
                        <p style="color:rgba(255,255,255,.8);font-size:14px;margin:8px 0 0;font-weight:600;">Himalayan Homestays</p>
                    </div>
                </div>

                <!-- Right: Content -->
                <div class="hhb-popup1-body">
                    <button class="hhb-popup-close" data-popup="hhb-popup1" aria-label="Close">&#x2715;</button>

                    <?php if ( $badge ) : ?>
                    <span class="hhb-popup1-badge"><?php echo $badge; ?></span>
                    <?php endif; ?>

                    <h2 class="hhb-popup1-headline"><?php echo $headline; ?></h2>
                    <p class="hhb-popup1-subtext"><?php echo $subtext; ?></p>

                    <?php if ( $coupon ) : ?>
                    <div class="hhb-popup1-coupon">
                        <span class="hhb-popup1-coupon-code" id="hhb-p1-coupon"><?php echo $coupon; ?></span>
                        <button class="hhb-popup1-copy-btn" onclick="hhbCopyCode('<?php echo esc_js( $coupon ); ?>', this)" title="Copy code">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                        </button>
                    </div>
                    <?php endif; ?>

                    <a href="<?php echo $btn_url; ?>" class="hhb-popup1-btn" onclick="hhbDismissPopup('hhb-popup1', <?php echo $cookie_days; ?>)"><?php echo $btn_text; ?></a>

                    <?php if ( $dismiss ) : ?>
                    <button class="hhb-popup-dismiss" onclick="hhbDismissPopup('hhb-popup1', <?php echo $cookie_days; ?>)"><?php echo $dismiss; ?></button>
                    <?php endif; ?>

                    <?php if ( $fine_print ) : ?>
                    <p class="hhb-popup1-fine"><?php echo $fine_print; ?></p>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Popup 2 HTML — Exit Intent / Free Guide
    // -------------------------------------------------------------------------
    private static function output_popup2(): void {
        $g = fn( $k ) => \Himalayan\Homestay\Interface\Admin\PopupsPage::get( $k );

        $logo            = esc_url( $g( 'p2_image' ) );
        $headline        = esc_html( $g( 'p2_headline' ) );
        $subtext         = esc_html( $g( 'p2_subtext' ) );
        $check1          = esc_html( $g( 'p2_check1' ) );
        $check2          = esc_html( $g( 'p2_check2' ) );
        $check3          = esc_html( $g( 'p2_check3' ) );
        $placeholder     = esc_attr( $g( 'p2_email_placeholder' ) );
        $btn_text        = esc_html( $g( 'p2_btn_text' ) );
        $subscriber_text = esc_html( $g( 'p2_subscriber_text' ) );
        $dismiss         = esc_html( $g( 'p2_dismiss' ) );
        $cookie_days     = (int) $g( 'p2_cookie_days' );
        $trigger         = $g( 'p2_trigger' );
        $delay           = (int) $g( 'p2_delay' );
        $scroll_pct      = (int) $g( 'p2_scroll_pct' );
        $nonce           = wp_create_nonce( 'hhb_newsletter' );
        ?>
        <div id="hhb-popup2" class="hhb-popup-overlay" aria-modal="true" role="dialog" aria-label="Free Guide"
             data-cookie-days="<?php echo $cookie_days; ?>"
             data-trigger="<?php echo esc_attr( $trigger ); ?>"
             data-delay="<?php echo $delay; ?>"
             data-scroll="<?php echo $scroll_pct; ?>">
            <div class="hhb-popup2-card">

                <button class="hhb-popup-close" data-popup="hhb-popup2" aria-label="Close">&#x2715;</button>

                <?php if ( $logo ) : ?>
                <div class="hhb-popup2-logo"><img src="<?php echo $logo; ?>" alt="Logo" style="width:72px;height:72px;object-fit:cover;border-radius:50%;border:3px solid #fef3eb;box-shadow:0 4px 16px rgba(232,94,48,.15);"></div>
                <?php else : ?>
                <div class="hhb-popup2-icon">🏔️</div>
                <?php endif; ?>
                <h2 class="hhb-popup2-headline"><?php echo $headline; ?></h2>
                <p class="hhb-popup2-subtext"><?php echo $subtext; ?></p>

                <?php if ( $check1 || $check2 || $check3 ) : ?>
                <ul class="hhb-popup2-checklist">
                    <?php foreach ( [ $check1, $check2, $check3 ] as $item ) : if ( ! $item ) continue; ?>
                    <li>
                        <span class="hhb-popup2-check-icon">✓</span>
                        <?php echo $item; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <div id="hhb-p2-form-wrap">
                    <div class="hhb-popup2-input-row">
                        <input type="email" id="hhb-p2-email" class="hhb-popup2-email-input" placeholder="<?php echo $placeholder; ?>" autocomplete="email">
                    </div>
                    <button class="hhb-popup2-btn" id="hhb-p2-submit"
                            data-nonce="<?php echo esc_attr( $nonce ); ?>"
                            data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
                            data-cookie="<?php echo $cookie_days; ?>">
                        <?php echo $btn_text; ?>
                    </button>
                    <div id="hhb-p2-msg" style="display:none;margin-top:10px;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:600;text-align:center;"></div>
                </div>

                <?php if ( $subscriber_text ) : ?>
                <p class="hhb-popup2-trust"><?php echo $subscriber_text; ?></p>
                <?php endif; ?>

                <?php if ( $dismiss ) : ?>
                <button class="hhb-popup-dismiss" onclick="hhbDismissPopup('hhb-popup2', <?php echo $cookie_days; ?>)"><?php echo $dismiss; ?></button>
                <?php endif; ?>

            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Shared styles
    // -------------------------------------------------------------------------
    private static function output_styles(): void {
        ?>
        <style id="hhb-popup-styles">
        /* ── Shared overlay ── */
        .hhb-popup-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 999999;
            align-items: center;
            justify-content: center;
            background: rgba(15,23,42,.6);
            backdrop-filter: blur(6px);
            padding: 16px;
        }
        .hhb-popup-overlay.hhb-open { display: flex; }

        @keyframes hhbPopIn {
            from { opacity:0; transform:scale(.92) translateY(16px); }
            to   { opacity:1; transform:scale(1)  translateY(0); }
        }

        .hhb-popup-close {
            position: absolute;
            top: 14px;
            right: 14px;
            background: rgba(0,0,0,.06);
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            font-size: 14px;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background .2s, color .2s;
            z-index: 10;
        }
        .hhb-popup-close:hover { background: #fee2e2; color: #dc2626; }

        .hhb-popup-dismiss {
            display: block;
            width: 100%;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 12px;
            color: #94a3b8;
            padding: 8px 0 2px;
            text-align: center;
            font-family: inherit;
            transition: color .2s;
        }
        .hhb-popup-dismiss:hover { color: #475569; }

        /* ── Popup 1 — Special Offer ── */
        .hhb-popup1-card {
            display: grid;
            grid-template-columns: 240px 1fr;
            max-width: 680px;
            width: 100%;
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 32px 80px rgba(0,0,0,.25);
            animation: hhbPopIn .3s ease;
            position: relative;
        }
        @media (max-width: 560px) {
            .hhb-popup1-card { grid-template-columns: 1fr; }
            .hhb-popup1-img  { height: 140px !important; }
        }
        .hhb-popup1-img {
            background-size: cover;
            background-position: center;
            position: relative;
            min-height: 380px;
        }
        .hhb-popup1-img-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(26,26,46,.3) 0%, rgba(26,26,46,.7) 100%);
        }
        .hhb-popup1-img-text {
            position: absolute;
            bottom: 24px;
            left: 24px;
            text-align: left;
        }
        .hhb-popup1-body {
            padding: 36px 32px 28px;
            display: flex;
            flex-direction: column;
            gap: 0;
            position: relative;
            font-family: 'Inter', -apple-system, sans-serif;
        }
        .hhb-popup1-badge {
            display: inline-block;
            background: #fef3eb;
            color: #e85e30;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 14px;
            width: fit-content;
        }
        .hhb-popup1-headline {
            font-size: 22px;
            font-weight: 900;
            color: #0f172a;
            margin: 0 0 10px;
            line-height: 1.2;
        }
        .hhb-popup1-subtext {
            font-size: 13px;
            color: #64748b;
            margin: 0 0 20px;
            line-height: 1.6;
        }
        .hhb-popup1-coupon {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8fafc;
            border: 2px dashed #e85e30;
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 18px;
        }
        .hhb-popup1-coupon-code {
            font-size: 18px;
            font-weight: 900;
            color: #e85e30;
            letter-spacing: 2px;
        }
        .hhb-popup1-copy-btn {
            background: #e85e30;
            border: none;
            border-radius: 6px;
            color: #fff;
            padding: 6px 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: background .2s;
        }
        .hhb-popup1-copy-btn:hover { background: #c94d22; }
        .hhb-popup1-btn {
            display: block;
            background: #e85e30;
            color: #fff;
            text-align: center;
            text-decoration: none;
            font-size: 15px;
            font-weight: 800;
            padding: 13px 24px;
            border-radius: 10px;
            transition: background .2s, transform .15s;
            margin-bottom: 4px;
        }
        .hhb-popup1-btn:hover { background: #c94d22; transform: translateY(-1px); color: #fff; }
        .hhb-popup1-fine {
            font-size: 11px;
            color: #94a3b8;
            text-align: center;
            margin: 8px 0 0;
        }

        /* ── Popup 2 — Free Guide ── */
        .hhb-popup2-card {
            background: linear-gradient(160deg, #fff 0%, #fff8f5 100%);
            border-radius: 24px;
            padding: 44px 40px 36px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 32px 80px rgba(0,0,0,.22);
            animation: hhbPopIn .3s ease;
            position: relative;
            font-family: 'Inter', -apple-system, sans-serif;
        }
        @media (max-width: 540px) { .hhb-popup2-card { padding: 36px 24px 28px; } }
        .hhb-popup2-icon { font-size: 52px; margin-bottom: 16px; line-height: 1; }
        .hhb-popup2-headline {
            font-size: 26px;
            font-weight: 900;
            color: #0f172a;
            margin: 0 0 10px;
        }
        .hhb-popup2-subtext {
            font-size: 14px;
            color: #64748b;
            margin: 0 0 20px;
            line-height: 1.6;
        }
        .hhb-popup2-checklist {
            list-style: none;
            padding: 0;
            margin: 0 0 22px;
            text-align: left;
        }
        .hhb-popup2-checklist li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            padding: 5px 0;
        }
        .hhb-popup2-check-icon {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
            background: #e85e30;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 900;
            margin-top: 1px;
        }
        .hhb-popup2-input-row { margin-bottom: 10px; }
        .hhb-popup2-email-input {
            width: 100%;
            padding: 13px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            box-sizing: border-box;
            outline: none;
            transition: border-color .2s;
        }
        .hhb-popup2-email-input:focus { border-color: #e85e30; box-shadow: 0 0 0 3px rgba(232,94,48,.1); }
        .hhb-popup2-btn {
            width: 100%;
            display: block;
            background: linear-gradient(135deg, #e85e30 0%, #c94d22 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px 24px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            font-family: inherit;
            transition: opacity .2s, transform .15s;
        }
        .hhb-popup2-btn:hover { opacity: .92; transform: translateY(-1px); }
        .hhb-popup2-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }
        .hhb-popup2-trust {
            font-size: 11px;
            color: #94a3b8;
            margin: 12px 0 4px;
        }
        </style>
        <?php
    }

    // -------------------------------------------------------------------------
    // Shared JS — triggers, cookies, copy, submit
    // -------------------------------------------------------------------------
    private static function output_scripts( bool $p1, bool $p2 ): void {
        ?>
        <script>
        (function(){

            // ── Cookie helpers ──────────────────────────────────────────────
            function setCookie(name, val, days) {
                var exp = '';
                if (days) {
                    var d = new Date();
                    d.setTime(d.getTime() + days * 864e5);
                    exp = '; expires=' + d.toUTCString();
                }
                document.cookie = name + '=' + val + exp + '; path=/';
            }
            function getCookie(name) {
                var v = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
                return v ? v.pop() : '';
            }

            // ── Open / dismiss ──────────────────────────────────────────────
            function openPopup(id) {
                var el = document.getElementById(id);
                if (el) { el.classList.add('hhb-open'); document.body.style.overflow = 'hidden'; }
            }
            window.hhbDismissPopup = function(id, days) {
                var el = document.getElementById(id);
                if (el) { el.classList.remove('hhb-open'); document.body.style.overflow = ''; }
                if (days > 0) setCookie('hhb_popup_' + id, '1', days);
            };

            // Close on backdrop click
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('hhb-popup-overlay')) {
                    var days = parseInt(e.target.dataset.cookieDays) || 0;
                    window.hhbDismissPopup(e.target.id, days);
                }
                if (e.target.classList.contains('hhb-popup-close') || e.target.closest('.hhb-popup-close')) {
                    var btn = e.target.closest('.hhb-popup-close');
                    var popup = document.getElementById(btn.dataset.popup);
                    if (popup) {
                        var days = parseInt(popup.dataset.cookieDays) || 0;
                        window.hhbDismissPopup(popup.id, days);
                    }
                }
            });
            // Close on ESC
            document.addEventListener('keydown', function(e) {
                if (e.key !== 'Escape') return;
                document.querySelectorAll('.hhb-popup-overlay.hhb-open').forEach(function(p){
                    var days = parseInt(p.dataset.cookieDays) || 0;
                    window.hhbDismissPopup(p.id, days);
                });
            });

            // ── Setup trigger for a popup ───────────────────────────────────
            function setupTrigger(id) {
                var el = document.getElementById(id);
                if (!el) return;

                // Already seen?
                if (getCookie('hhb_popup_' + id)) return;

                var trigger    = el.dataset.trigger;
                var delay      = parseInt(el.dataset.delay)  * 1000 || 5000;
                var scrollPct  = parseInt(el.dataset.scroll) || 50;

                if (trigger === 'delay') {
                    setTimeout(function(){ openPopup(id); }, delay);

                } else if (trigger === 'exit') {
                    var fired = false;
                    document.addEventListener('mouseleave', function(e) {
                        if (fired || e.clientY > 20) return;
                        fired = true;
                        openPopup(id);
                    });

                } else if (trigger === 'scroll') {
                    var fired = false;
                    window.addEventListener('scroll', function() {
                        if (fired) return;
                        var pct = (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100;
                        if (pct >= scrollPct) { fired = true; openPopup(id); }
                    }, { passive: true });
                }
            }

            <?php if ( $p1 ) : ?>setupTrigger('hhb-popup1');<?php endif; ?>
            <?php if ( $p2 ) : ?>setupTrigger('hhb-popup2');<?php endif; ?>

            // ── Copy coupon code ────────────────────────────────────────────
            window.hhbCopyCode = function(code, btn) {
                navigator.clipboard.writeText(code).then(function(){
                    btn.innerHTML = '✓';
                    setTimeout(function(){ btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>'; }, 2000);
                });
            };

            <?php if ( $p2 ) : ?>
            // ── Popup 2 email submit ────────────────────────────────────────
            var p2btn = document.getElementById('hhb-p2-submit');
            if (p2btn) {
                p2btn.addEventListener('click', function(){
                    var email    = document.getElementById('hhb-p2-email').value.trim();
                    var msg      = document.getElementById('hhb-p2-msg');
                    var nonce    = p2btn.dataset.nonce;
                    var ajaxUrl  = p2btn.dataset.ajax;
                    var cookieDays = parseInt(p2btn.dataset.cookie) || 0;

                    if (!email) { showP2Msg('Please enter your email address.', false); return; }

                    p2btn.disabled = true;
                    p2btn.textContent = 'Sending…';

                    var fd = new FormData();
                    fd.append('action', 'hhb_popup2_subscribe');
                    fd.append('nonce',  nonce);
                    fd.append('email',  email);

                    fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        showP2Msg(res.data ? res.data.message : 'Something went wrong.', res.success);
                        if (res.success) {
                            document.getElementById('hhb-p2-form-wrap').style.opacity = '.5';
                            document.getElementById('hhb-p2-form-wrap').style.pointerEvents = 'none';
                            setTimeout(function(){ window.hhbDismissPopup('hhb-popup2', cookieDays); }, 2500);
                        }
                    })
                    .catch(function(){ showP2Msg('Network error. Please try again.', false); })
                    .finally(function(){
                        p2btn.disabled = false;
                        p2btn.textContent = p2btn.dataset.text || 'Send Me the Free Guide →';
                    });
                });

                function showP2Msg(text, ok) {
                    var msg = document.getElementById('hhb-p2-msg');
                    msg.style.display     = 'block';
                    msg.style.background  = ok ? '#f0fdf4' : '#fef2f2';
                    msg.style.color       = ok ? '#16a34a' : '#dc2626';
                    msg.style.border      = ok ? '1px solid #bbf7d0' : '1px solid #fecaca';
                    msg.textContent       = text;
                }
            }
            <?php endif; ?>

        })();
        </script>
        <?php
    }
}
