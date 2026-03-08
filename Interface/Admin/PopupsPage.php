<?php
/**
 * Promotional Popups Admin Page
 *
 * Full settings UI for 2 promotional popups — everything customizable:
 * text, images, triggers, page visibility, button links.
 *
 * @package Himalayan\Homestay\Interface\Admin
 */

namespace Himalayan\Homestay\Interface\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PopupsPage {

    const OPT = 'hhb_popups_settings';

    /** Default values for all settings */
    private static array $defaults = [
        // ── Popup 1 — Special Offer ──────────────────────────────────────────
        'p1_enabled'      => 0,
        'p1_trigger'      => 'delay',
        'p1_delay'        => 5,
        'p1_scroll_pct'   => 50,
        'p1_cookie_days'  => 3,
        'p1_pages'        => 'all',
        'p1_image'        => '',
        'p1_badge'        => 'LIMITED TIME OFFER',
        'p1_headline'     => 'Get 20% Off Your First Booking',
        'p1_subtext'      => 'Use code WELCOME20 at checkout. Valid for new guests only. Expires soon.',
        'p1_coupon'       => 'WELCOME20',
        'p1_btn_text'     => 'Claim Your Discount',
        'p1_btn_url'      => '',
        'p1_dismiss'      => "No thanks, I'll pay full price",
        'p1_fine_print'   => 'Offer valid on stays of 2+ nights',

        // ── Popup 2 — Exit Intent / Free Guide ───────────────────────────────
        'p2_enabled'      => 0,
        'p2_trigger'      => 'exit',
        'p2_delay'        => 3,
        'p2_scroll_pct'   => 60,
        'p2_cookie_days'  => 7,
        'p2_pages'        => 'all',
        'p2_image'        => '',
        'p2_headline'     => 'Before You Go...',
        'p2_subtext'      => 'Get our FREE Himalayan Travel Guide + exclusive member-only deals straight to your inbox.',
        'p2_check1'       => 'Hidden homestays not listed anywhere else',
        'p2_check2'       => 'Seasonal deals up to 40% off',
        'p2_check3'       => 'Insider tips from local hosts',
        'p2_email_placeholder' => 'Enter your email',
        'p2_btn_text'     => 'Send Me the Free Guide →',
        'p2_subscriber_text' => 'Join 2,400+ travelers. Unsubscribe anytime.',
        'p2_dismiss'      => "No thanks, I prefer to travel blind",
    ];

    // -------------------------------------------------------------------------

    public static function init(): void {
        add_action( 'admin_menu',             [ __CLASS__, 'add_menu_page' ], 70 );
        add_action( 'admin_post_hhb_save_popups', [ __CLASS__, 'save_settings' ] );
        add_action( 'admin_enqueue_scripts',  [ __CLASS__, 'enqueue_media' ] );
    }

    public static function add_menu_page(): void {
        add_submenu_page(
            'edit.php?post_type=hhb_homestay',
            __( 'Promotional Popups', 'himalayan-homestay-bookings' ),
            __( 'Popups', 'himalayan-homestay-bookings' ),
            'manage_options',
            'hhb-popups',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function enqueue_media(): void {
        $page = $_GET['page'] ?? '';
        if ( $page !== 'hhb-popups' ) return;
        wp_enqueue_media();
    }

    /** Get a single setting with default fallback */
    public static function get( string $key ): string {
        $opts = get_option( self::OPT, [] );
        return (string) ( $opts[ $key ] ?? self::$defaults[ $key ] ?? '' );
    }

    public static function save_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'hhb_save_popups' );

        $saved = [];

        foreach ( self::$defaults as $key => $default ) {
            $raw = $_POST[ $key ] ?? '';

            if ( in_array( $key, [ 'p1_enabled', 'p2_enabled' ], true ) ) {
                $saved[ $key ] = isset( $_POST[ $key ] ) ? 1 : 0;
            } elseif ( in_array( $key, [ 'p1_delay', 'p2_delay', 'p1_scroll_pct', 'p2_scroll_pct', 'p1_cookie_days', 'p2_cookie_days' ], true ) ) {
                $saved[ $key ] = max( 0, (int) $raw );
            } elseif ( $key === 'p1_btn_url' ) {
                $saved[ $key ] = esc_url_raw( $raw );
            } elseif ( in_array( $key, [ 'p1_subtext', 'p2_subtext' ], true ) ) {
                $saved[ $key ] = wp_kses_post( $raw );
            } else {
                $saved[ $key ] = sanitize_text_field( $raw );
            }
        }

        update_option( self::OPT, $saved );

        wp_safe_redirect( add_query_arg( [
            'post_type' => 'hhb_homestay',
            'page'      => 'hhb-popups',
            'saved'     => '1',
        ], admin_url( 'edit.php' ) ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // Admin page renderer
    // -------------------------------------------------------------------------

    public static function render_page(): void {
        $tab = isset( $_GET['tab'] ) && $_GET['tab'] === 'popup2' ? 'popup2' : 'popup1';
        ?>
        <div class="wrap" id="hhb-popups-admin">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <span class="dashicons dashicons-megaphone" style="font-size:28px;width:28px;height:28px;color:#e85e30;"></span>
            Promotional Popups
        </h1>

        <?php if ( isset( $_GET['saved'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>
        <?php endif; ?>

        <style>
        #hhb-popups-admin { max-width:900px; }
        .hhbp-tabs { display:flex;gap:0;margin:20px 0 0;border-bottom:2px solid #e0e0e0; }
        .hhbp-tab  { padding:10px 24px;font-size:14px;font-weight:700;cursor:pointer;border:none;background:none;border-bottom:2px solid transparent;margin-bottom:-2px;color:#666;text-decoration:none;display:inline-block; }
        .hhbp-tab.active { border-bottom-color:#e85e30;color:#e85e30; }
        .hhbp-panel  { background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:32px;margin-top:20px;box-shadow:0 1px 4px rgba(0,0,0,.06); }
        .hhbp-section { margin-bottom:32px;padding-bottom:28px;border-bottom:1px solid #f5f5f5; }
        .hhbp-section:last-child { border-bottom:none;margin-bottom:0;padding-bottom:0; }
        .hhbp-section-title { font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:#e85e30;margin:0 0 18px; }
        .hhbp-row   { display:grid;grid-template-columns:200px 1fr;gap:12px 20px;align-items:start;margin-bottom:14px; }
        .hhbp-row label { font-size:13px;font-weight:600;color:#374151;padding-top:8px; }
        .hhbp-row small { display:block;color:#94a3b8;font-size:11px;margin-top:3px; }
        .hhbp-input  { width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:7px;font-size:13px;box-sizing:border-box; }
        .hhbp-input:focus { border-color:#e85e30;outline:none;box-shadow:0 0 0 3px rgba(232,94,48,.1); }
        .hhbp-select { width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:7px;font-size:13px;background:#fff;box-sizing:border-box; }
        .hhbp-textarea { width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:7px;font-size:13px;min-height:80px;resize:vertical;box-sizing:border-box; }
        .hhbp-toggle { display:flex;align-items:center;gap:10px;padding-top:6px; }
        .hhbp-toggle input[type=checkbox] { width:18px;height:18px;accent-color:#e85e30;cursor:pointer; }
        .hhbp-toggle span { font-size:13px;color:#374151;font-weight:600; }
        .hhbp-img-wrap { display:flex;align-items:center;gap:10px;flex-wrap:wrap; }
        .hhbp-img-preview { width:80px;height:60px;object-fit:cover;border-radius:6px;border:1px solid #e0e0e0;display:none; }
        .hhbp-img-btn { padding:7px 14px;background:#f8f8f8;border:1px solid #d1d5db;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer; }
        .hhbp-img-remove { padding:7px 12px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;color:#dc2626;display:none; }
        .hhbp-trigger-opts { display:flex;flex-direction:column;gap:6px;padding-top:6px; }
        .hhbp-trigger-opts label { display:flex;align-items:center;gap:8px;font-size:13px;color:#374151;cursor:pointer;font-weight:500; }
        .hhbp-sub-opt { margin-top:8px;margin-left:24px;display:none; }
        .hhbp-sub-opt.visible { display:flex;align-items:center;gap:8px; }
        .hhbp-num { width:70px;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;text-align:center; }
        .hhbp-save-btn { background:#e85e30;color:#fff;border:none;padding:12px 32px;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;margin-top:8px; }
        .hhbp-save-btn:hover { background:#c94d22; }
        .hhbp-badge { display:inline-block;background:#fef3eb;color:#e85e30;font-size:11px;font-weight:800;padding:2px 10px;border-radius:20px;margin-left:8px; }
        </style>

        <!-- Tab Navigation -->
        <div class="hhbp-tabs">
            <a href="?post_type=hhb_homestay&page=hhb-popups&tab=popup1" class="hhbp-tab <?php echo $tab === 'popup1' ? 'active' : ''; ?>">
                Popup 1 — Special Offer
                <?php if ( self::get( 'p1_enabled' ) ) echo '<span class="hhbp-badge">ON</span>'; ?>
            </a>
            <a href="?post_type=hhb_homestay&page=hhb-popups&tab=popup2" class="hhbp-tab <?php echo $tab === 'popup2' ? 'active' : ''; ?>">
                Popup 2 — Free Guide / Exit Intent
                <?php if ( self::get( 'p2_enabled' ) ) echo '<span class="hhbp-badge">ON</span>'; ?>
            </a>
        </div>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'hhb_save_popups' ); ?>
            <input type="hidden" name="action" value="hhb_save_popups">

            <?php if ( $tab === 'popup1' ) : ?>
            <!-- ══ POPUP 1 ══════════════════════════════════════════════════════ -->
            <div class="hhbp-panel">

                <!-- Enable -->
                <div class="hhbp-section">
                    <p class="hhbp-section-title">Status</p>
                    <div class="hhbp-toggle">
                        <input type="checkbox" name="p1_enabled" id="p1_enabled" value="1" <?php checked( 1, (int) self::get( 'p1_enabled' ) ); ?>>
                        <span>Enable Popup 1 (Special Offer)</span>
                    </div>
                </div>

                <!-- Trigger -->
                <div class="hhbp-section">
                    <p class="hhbp-section-title">Trigger</p>
                    <div class="hhbp-row">
                        <label>Show popup when</label>
                        <div>
                            <div class="hhbp-trigger-opts" id="p1-trigger-opts">
                                <?php foreach ( [ 'delay' => 'Page loads (after a delay)', 'exit' => 'User tries to leave (exit intent)', 'scroll' => 'User scrolls down' ] as $val => $label ) : ?>
                                <label>
                                    <input type="radio" name="p1_trigger" value="<?php echo $val; ?>" <?php checked( self::get( 'p1_trigger' ), $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="hhbp-sub-opt <?php echo self::get( 'p1_trigger' ) === 'delay' ? 'visible' : ''; ?>" id="p1-delay-opt">
                                Show after <input type="number" name="p1_delay" value="<?php echo esc_attr( self::get( 'p1_delay' ) ); ?>" min="0" max="60" class="hhbp-num"> seconds
                            </div>
                            <div class="hhbp-sub-opt <?php echo self::get( 'p1_trigger' ) === 'scroll' ? 'visible' : ''; ?>" id="p1-scroll-opt">
                                Show after scrolling <input type="number" name="p1_scroll_pct" value="<?php echo esc_attr( self::get( 'p1_scroll_pct' ) ); ?>" min="10" max="90" class="hhbp-num"> % of the page
                            </div>
                        </div>
                    </div>
                    <div class="hhbp-row">
                        <label>Don't show again for<small>After a visitor closes the popup, hide it for this many days. Set 0 to show on every visit.</small></label>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <input type="number" name="p1_cookie_days" value="<?php echo esc_attr( self::get( 'p1_cookie_days' ) ); ?>" min="0" max="365" class="hhbp-num"> <span style="font-size:13px;color:#475569;">days &nbsp;(0 = show every visit)</span>
                        </div>
                    </div>
                    <div class="hhbp-row">
                        <label>Show on pages</label>
                        <select name="p1_pages" class="hhbp-select" style="max-width:300px;">
                            <?php foreach ( [ 'all' => 'All pages', 'home' => 'Homepage only', 'archive' => 'Homestay archive', 'single' => 'Single homestay', 'blog' => 'Blog posts' ] as $val => $label ) : ?>
                            <option value="<?php echo $val; ?>" <?php selected( self::get( 'p1_pages' ), $val ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Image -->
                <div class="hhbp-section">
                    <p class="hhbp-section-title">Background Image (Left Panel)</p>
                    <div class="hhbp-row">
                        <label>Image<small>Shown on left side of popup</small></label>
                        <div>
                            <div class="hhbp-img-wrap">
                                <img id="p1-img-preview" class="hhbp-img-preview" src="<?php echo esc_url( self::get( 'p1_image' ) ); ?>" style="<?php echo self::get( 'p1_image' ) ? 'display:block;' : ''; ?>">
                                <button type="button" class="hhbp-img-btn" id="p1-img-select">Choose Image</button>
                                <button type="button" class="hhbp-img-remove" id="p1-img-remove" style="<?php echo self::get( 'p1_image' ) ? 'display:inline-block;' : ''; ?>">Remove</button>
                            </div>
                            <input type="hidden" name="p1_image" id="p1-image-url" value="<?php echo esc_attr( self::get( 'p1_image' ) ); ?>">
                            <small style="margin-top:8px;display:block;color:#94a3b8;">Recommended: landscape photo, min 600×800px</small>
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div class="hhbp-section">
                    <p class="hhbp-section-title">Content</p>

                    <?php self::field( 'p1_badge', 'Badge text', 'Small label above headline e.g. "LIMITED TIME OFFER"' ); ?>
                    <?php self::field( 'p1_headline', 'Headline' ); ?>
                    <?php self::field( 'p1_subtext', 'Subtext', 'Shown below headline', 'textarea' ); ?>
                    <?php self::field( 'p1_coupon', 'Coupon code', 'Displayed in a copyable box. Leave blank to hide.' ); ?>
                    <?php self::field( 'p1_btn_text', 'Button text' ); ?>
                    <?php self::field( 'p1_btn_url', 'Button URL', 'Where does the button take the user? e.g. /homestays/' ); ?>
                    <?php self::field( 'p1_dismiss', 'Dismiss link text', '"No thanks" link below button' ); ?>
                    <?php self::field( 'p1_fine_print', 'Fine print', 'Small text at very bottom' ); ?>
                </div>

            </div>
            <?php else : ?>
            <!-- ══ POPUP 2 ══════════════════════════════════════════════════════ -->
            <div class="hhbp-panel">

                <!-- Enable -->
                <div class="hhbp-section">
                    <p class="hhbp-section-title">Status</p>
                    <div class="hhbp-toggle">
                        <input type="checkbox" name="p2_enabled" id="p2_enabled" value="1" <?php checked( 1, (int) self::get( 'p2_enabled' ) ); ?>>
                        <span>Enable Popup 2 (Free Guide / Exit Intent)</span>
                    </div>
                </div>

                <!-- Trigger -->
                <div class="hhbp-section">
                    <p class="hhbp-section-title">Trigger</p>
                    <div class="hhbp-row">
                        <label>Show popup when</label>
                        <div>
                            <div class="hhbp-trigger-opts" id="p2-trigger-opts">
                                <?php foreach ( [ 'delay' => 'Page loads (after a delay)', 'exit' => 'User tries to leave (exit intent)', 'scroll' => 'User scrolls down' ] as $val => $label ) : ?>
                                <label>
                                    <input type="radio" name="p2_trigger" value="<?php echo $val; ?>" <?php checked( self::get( 'p2_trigger' ), $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="hhbp-sub-opt <?php echo self::get( 'p2_trigger' ) === 'delay' ? 'visible' : ''; ?>" id="p2-delay-opt">
                                Show after <input type="number" name="p2_delay" value="<?php echo esc_attr( self::get( 'p2_delay' ) ); ?>" min="0" max="60" class="hhbp-num"> seconds
                            </div>
                            <div class="hhbp-sub-opt <?php echo self::get( 'p2_trigger' ) === 'scroll' ? 'visible' : ''; ?>" id="p2-scroll-opt">
                                Show after scrolling <input type="number" name="p2_scroll_pct" value="<?php echo esc_attr( self::get( 'p2_scroll_pct' ) ); ?>" min="10" max="90" class="hhbp-num"> % of the page
                            </div>
                        </div>
                    </div>
                    <div class="hhbp-row">
                        <label>Don't show again for<small>After a visitor closes the popup, hide it for this many days. Set 0 to show on every visit.</small></label>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <input type="number" name="p2_cookie_days" value="<?php echo esc_attr( self::get( 'p2_cookie_days' ) ); ?>" min="0" max="365" class="hhbp-num"> <span style="font-size:13px;color:#475569;">days &nbsp;(0 = show every visit)</span>
                        </div>
                    </div>
                    <div class="hhbp-row">
                        <label>Show on pages</label>
                        <select name="p2_pages" class="hhbp-select" style="max-width:300px;">
                            <?php foreach ( [ 'all' => 'All pages', 'home' => 'Homepage only', 'archive' => 'Homestay archive', 'single' => 'Single homestay', 'blog' => 'Blog posts' ] as $val => $label ) : ?>
                            <option value="<?php echo $val; ?>" <?php selected( self::get( 'p2_pages' ), $val ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Logo / Icon -->
                <div class="hhbp-section">
                    <p class="hhbp-section-title">Logo / Icon Image</p>
                    <div class="hhbp-row">
                        <label for="p2-image-url">Logo image<small>Shown at top of popup. Replaces the 🏔️ emoji. Recommended: square, min 120×120px</small></label>
                        <div>
                            <div class="hhbp-img-wrap">
                                <img id="p2-img-preview" class="hhbp-img-preview" src="<?php echo esc_url( self::get( 'p2_image' ) ); ?>" style="<?php echo self::get( 'p2_image' ) ? 'display:block;width:60px;height:60px;border-radius:50%;' : ''; ?>">
                                <button type="button" class="hhbp-img-btn" id="p2-img-select">Choose Image</button>
                                <button type="button" class="hhbp-img-remove" id="p2-img-remove" style="<?php echo self::get( 'p2_image' ) ? 'display:inline-block;' : ''; ?>">Remove</button>
                            </div>
                            <input type="hidden" name="p2_image" id="p2-image-url" value="<?php echo esc_attr( self::get( 'p2_image' ) ); ?>">
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div class="hhbp-section">
                    <p class="hhbp-section-title">Content</p>

                    <?php self::field( 'p2_headline', 'Headline' ); ?>
                    <?php self::field( 'p2_subtext', 'Subtext', 'Shown below headline', 'textarea' ); ?>
                </div>

                <!-- Checklist -->
                <div class="hhbp-section">
                    <p class="hhbp-section-title">Benefit Checklist</p>
                    <?php self::field( 'p2_check1', 'Item 1' ); ?>
                    <?php self::field( 'p2_check2', 'Item 2' ); ?>
                    <?php self::field( 'p2_check3', 'Item 3' ); ?>
                </div>

                <!-- Form -->
                <div class="hhbp-section">
                    <p class="hhbp-section-title">Email Form</p>
                    <?php self::field( 'p2_email_placeholder', 'Input placeholder', 'Text shown inside the email field' ); ?>
                    <?php self::field( 'p2_btn_text', 'Button text' ); ?>
                    <?php self::field( 'p2_subscriber_text', 'Trust line', 'Small text below button e.g. "Join 2,400+ travelers"' ); ?>
                    <?php self::field( 'p2_dismiss', 'Dismiss link text' ); ?>
                </div>

            </div>
            <?php endif; ?>

            <p style="margin-top:20px;">
                <button type="submit" class="hhbp-save-btn">Save Settings</button>
            </p>
        </form>
        </div>

        <script>
        jQuery(function($){
            // ── Trigger radio toggle ────────────────────────────────────────
            function bindTrigger(prefix) {
                var radios = document.querySelectorAll('input[name="' + prefix + '_trigger"]');
                var delayOpt  = document.getElementById(prefix + '-delay-opt');
                var scrollOpt = document.getElementById(prefix + '-scroll-opt');
                if (!radios.length) return;

                function update() {
                    var val = $('input[name="' + prefix + '_trigger"]:checked').val();
                    delayOpt.className  = 'hhbp-sub-opt' + (val === 'delay'  ? ' visible' : '');
                    scrollOpt.className = 'hhbp-sub-opt' + (val === 'scroll' ? ' visible' : '');
                }
                radios.forEach(function(r){ r.addEventListener('change', update); });
            }
            bindTrigger('p1');
            bindTrigger('p2');

            // ── Media uploader (reusable) ───────────────────────────────────
            function bindMediaUploader(selectId, removeId, inputId, previewId) {
                var selectBtn = document.getElementById(selectId);
                var removeBtn = document.getElementById(removeId);
                var urlInput  = document.getElementById(inputId);
                var preview   = document.getElementById(previewId);
                if (!selectBtn) return;

                var frame;
                selectBtn.addEventListener('click', function(e){
                    e.preventDefault();
                    if (frame) { frame.open(); return; }
                    frame = wp.media({ title: 'Choose Image', button: { text: 'Use this image' }, multiple: false });
                    frame.on('select', function(){
                        var att = frame.state().get('selection').first().toJSON();
                        urlInput.value          = att.url;
                        preview.src             = att.url;
                        preview.style.display   = 'block';
                        removeBtn.style.display = 'inline-block';
                    });
                    frame.open();
                });

                removeBtn.addEventListener('click', function(){
                    urlInput.value          = '';
                    preview.src             = '';
                    preview.style.display   = 'none';
                    removeBtn.style.display = 'none';
                });
            }

            bindMediaUploader('p1-img-select', 'p1-img-remove', 'p1-image-url', 'p1-img-preview');
            bindMediaUploader('p2-img-select', 'p2-img-remove', 'p2-image-url', 'p2-img-preview');
        });
        </script>
        <?php
    }

    /** Helper: render a labeled input/textarea row */
    private static function field( string $key, string $label, string $hint = '', string $type = 'input' ): void {
        $val = self::get( $key );
        ?>
        <div class="hhbp-row">
            <label for="hhbp-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?>
                <?php if ( $hint ) : ?><small><?php echo esc_html( $hint ); ?></small><?php endif; ?>
            </label>
            <?php if ( $type === 'textarea' ) : ?>
                <textarea name="<?php echo esc_attr( $key ); ?>" id="hhbp-<?php echo esc_attr( $key ); ?>" class="hhbp-textarea"><?php echo esc_textarea( $val ); ?></textarea>
            <?php else : ?>
                <input type="text" name="<?php echo esc_attr( $key ); ?>" id="hhbp-<?php echo esc_attr( $key ); ?>" class="hhbp-input" value="<?php echo esc_attr( $val ); ?>">
            <?php endif; ?>
        </div>
        <?php
    }
}
