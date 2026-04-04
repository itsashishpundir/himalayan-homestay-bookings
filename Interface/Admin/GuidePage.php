<?php
/**
 * Plugin Setup Guide Page
 *
 * A single-page admin reference that walks site admins through
 * configuring every aspect of Himalayan Homestay Bookings —
 * pages to create, shortcodes, templates, roles, and the booking flow.
 *
 * @package Himalayan\Homestay\Interface\Admin
 */

namespace Himalayan\Homestay\Interface\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GuidePage {

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ], 99 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    public static function add_menu_page(): void {
        add_submenu_page(
            'edit.php?post_type=hhb_homestay',
            __( 'Setup Guide', 'himalayan-homestay-bookings' ),
            __( '📖 Setup Guide', 'himalayan-homestay-bookings' ),
            'manage_options',
            'hhb-guide',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function enqueue_assets( string $hook ): void {
        if ( false === strpos( $hook, 'hhb-guide' ) ) {
            return;
        }
        // Inline styles only — no external deps needed.
    }

    public static function render_page(): void {
        $new_page_url = admin_url( 'post-new.php?post_type=page' );
        $settings_url = admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-settings' );
        ?>
        <div class="wrap hhb-guide-wrap">

            <h1 style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
                <span style="font-size:28px;">📖</span>
                <?php esc_html_e( 'Himalayan Homestay — Setup Guide', 'himalayan-homestay-bookings' ); ?>
            </h1>
            <p style="color:#666;margin-top:0;margin-bottom:28px;font-size:14px;">
                <?php esc_html_e( 'Follow these steps to get your booking site fully configured.', 'himalayan-homestay-bookings' ); ?>
            </p>

            <style>
                .hhb-guide-wrap { max-width: 960px; }
                .hhb-guide-wrap .hhb-section {
                    background: #fff;
                    border: 1px solid #e0e0e0;
                    border-radius: 10px;
                    padding: 24px 28px;
                    margin-bottom: 24px;
                }
                .hhb-guide-wrap .hhb-section h2 {
                    margin: 0 0 6px 0;
                    font-size: 17px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    border-bottom: 1px solid #f0f0f0;
                    padding-bottom: 12px;
                    margin-bottom: 16px;
                }
                .hhb-guide-wrap .hhb-section h2 span.step-badge {
                    background: #2271b1;
                    color: #fff;
                    border-radius: 50%;
                    width: 26px;
                    height: 26px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 13px;
                    font-weight: 700;
                    flex-shrink: 0;
                }
                .hhb-guide-wrap table.hhb-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 13.5px;
                }
                .hhb-guide-wrap table.hhb-table th {
                    background: #f6f7f7;
                    text-align: left;
                    padding: 9px 12px;
                    border: 1px solid #e0e0e0;
                    font-weight: 600;
                    font-size: 12px;
                    text-transform: uppercase;
                    color: #555;
                }
                .hhb-guide-wrap table.hhb-table td {
                    padding: 9px 12px;
                    border: 1px solid #e0e0e0;
                    vertical-align: top;
                    line-height: 1.6;
                }
                .hhb-guide-wrap table.hhb-table tr:hover td { background: #fafafa; }
                .hhb-guide-wrap code.hhb-code {
                    background: #f0f6ff;
                    border: 1px solid #c8dcf5;
                    color: #1a56a4;
                    padding: 2px 7px;
                    border-radius: 4px;
                    font-size: 13px;
                    font-family: monospace;
                }
                .hhb-guide-wrap .hhb-note {
                    background: #fff8e5;
                    border-left: 4px solid #f0b429;
                    padding: 10px 14px;
                    border-radius: 0 6px 6px 0;
                    font-size: 13px;
                    color: #555;
                    margin-top: 14px;
                }
                .hhb-guide-wrap .hhb-tip {
                    background: #edfaed;
                    border-left: 4px solid #46b450;
                    padding: 10px 14px;
                    border-radius: 0 6px 6px 0;
                    font-size: 13px;
                    color: #1a5c1a;
                    margin-top: 14px;
                }
                .hhb-guide-wrap .hhb-flow {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    gap: 6px;
                    margin-top: 12px;
                    font-size: 13.5px;
                }
                .hhb-guide-wrap .hhb-flow .flow-box {
                    background: #f0f6ff;
                    border: 1px solid #c8dcf5;
                    border-radius: 6px;
                    padding: 8px 14px;
                    color: #1a56a4;
                    font-weight: 500;
                }
                .hhb-guide-wrap .hhb-flow .flow-arrow {
                    color: #999;
                    font-size: 18px;
                    line-height: 1;
                }
                .hhb-guide-wrap .hhb-btn {
                    display: inline-block;
                    background: #2271b1;
                    color: #fff;
                    padding: 7px 16px;
                    border-radius: 5px;
                    text-decoration: none;
                    font-size: 13px;
                    font-weight: 600;
                    margin-top: 10px;
                }
                .hhb-guide-wrap .hhb-btn:hover { background: #135e96; color: #fff; }
                .hhb-guide-wrap .hhb-btn-secondary {
                    background: #f6f7f7;
                    color: #2271b1;
                    border: 1px solid #c3c4c7;
                }
                .hhb-guide-wrap .hhb-btn-secondary:hover { background: #e8eaed; color: #135e96; }
                .hhb-guide-wrap ol.hhb-steps { margin: 0; padding-left: 20px; }
                .hhb-guide-wrap ol.hhb-steps li { margin-bottom: 8px; font-size: 13.5px; line-height: 1.7; }
            </style>

            <?php /* ===================== STEP 1: PAYMENT ===================== */ ?>
            <div class="hhb-section">
                <h2>
                    <span class="step-badge">1</span>
                    <?php esc_html_e( 'Configure Payment Gateways', 'himalayan-homestay-bookings' ); ?>
                    &nbsp;<span style="font-size:13px;color:#666;font-weight:400;"><?php esc_html_e( '— required before accepting bookings', 'himalayan-homestay-bookings' ); ?></span>
                </h2>
                <p style="font-size:13.5px;color:#444;margin-top:0;">
                    <?php esc_html_e( 'The plugin supports Razorpay and PayPal. Add your API keys so guests can pay online.', 'himalayan-homestay-bookings' ); ?>
                </p>
                <a href="<?php echo esc_url( $settings_url ); ?>" class="hhb-btn">
                    <?php esc_html_e( '⚙️ Open Settings Page', 'himalayan-homestay-bookings' ); ?>
                </a>
                <div class="hhb-note">
                    <?php esc_html_e( 'You can also configure SMTP email settings on the same Settings page so booking confirmation emails are delivered reliably.', 'himalayan-homestay-bookings' ); ?>
                </div>
            </div>

            <?php /* ===================== STEP 2: CREATE WP PAGES ===================== */ ?>
            <div class="hhb-section">
                <h2>
                    <span class="step-badge">2</span>
                    <?php esc_html_e( 'Create Required WordPress Pages', 'himalayan-homestay-bookings' ); ?>
                </h2>
                <p style="font-size:13.5px;color:#444;margin-top:0;">
                    <?php esc_html_e( 'Go to Pages → Add New for each row below. The plugin does not auto-create these pages.', 'himalayan-homestay-bookings' ); ?>
                </p>

                <table class="hhb-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Page Name', 'himalayan-homestay-bookings' ); ?></th>
                            <th><?php esc_html_e( 'Suggested Slug', 'himalayan-homestay-bookings' ); ?></th>
                            <th><?php esc_html_e( 'What to Add', 'himalayan-homestay-bookings' ); ?></th>
                            <th><?php esc_html_e( 'Purpose', 'himalayan-homestay-bookings' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e( 'My Account', 'himalayan-homestay-bookings' ); ?></strong></td>
                            <td><code class="hhb-code">/my-account</code></td>
                            <td>
                                <?php esc_html_e( 'Add shortcode:', 'himalayan-homestay-bookings' ); ?><br>
                                <code class="hhb-code">[hhb_my_account]</code>
                            </td>
                            <td><?php esc_html_e( 'Guest login, registration, view & cancel bookings, booking history.', 'himalayan-homestay-bookings' ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Host Dashboard', 'himalayan-homestay-bookings' ); ?></strong></td>
                            <td><code class="hhb-code">/host-panel</code></td>
                            <td>
                                <?php esc_html_e( 'Set Page Template to:', 'himalayan-homestay-bookings' ); ?><br>
                                <code class="hhb-code">host-panel-template</code>
                            </td>
                            <td><?php esc_html_e( 'Hosts manage their properties, rooms, bookings, calendar, payouts, and profile settings.', 'himalayan-homestay-bookings' ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Become a Host', 'himalayan-homestay-bookings' ); ?></strong></td>
                            <td><code class="hhb-code">/become-host</code></td>
                            <td>
                                <?php esc_html_e( 'Set Page Template to:', 'himalayan-homestay-bookings' ); ?><br>
                                <code class="hhb-code">become-host-template</code>
                            </td>
                            <td><?php esc_html_e( 'Public application form for new hosts to register their property.', 'himalayan-homestay-bookings' ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Privacy Policy / Terms', 'himalayan-homestay-bookings' ); ?></strong></td>
                            <td><code class="hhb-code">/privacy-policy</code></td>
                            <td>
                                <?php esc_html_e( 'Set Page Template to:', 'himalayan-homestay-bookings' ); ?><br>
                                <code class="hhb-code">legal-page-template</code>
                            </td>
                            <td><?php esc_html_e( 'Styled legal content page with hero, breadcrumbs, and formatted body text.', 'himalayan-homestay-bookings' ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Data Deletion Request', 'himalayan-homestay-bookings' ); ?></strong></td>
                            <td><code class="hhb-code">/data-deletion</code></td>
                            <td>
                                <?php esc_html_e( 'Add shortcode:', 'himalayan-homestay-bookings' ); ?><br>
                                <code class="hhb-code">[hhb_data_deletion_request]</code>
                            </td>
                            <td><?php esc_html_e( 'GDPR/CCPA compliance — users enter their email and request permanent deletion of all their personal data.', 'himalayan-homestay-bookings' ); ?></td>
                        </tr>
                    </tbody>
                </table>

                <a href="<?php echo esc_url( $new_page_url ); ?>" class="hhb-btn" target="_blank">
                    <?php esc_html_e( '+ Create a New Page', 'himalayan-homestay-bookings' ); ?>
                </a>

                <div class="hhb-tip">
                    <strong><?php esc_html_e( 'How to set a Page Template:', 'himalayan-homestay-bookings' ); ?></strong>
                    <?php esc_html_e( ' When editing a page in WordPress, look for the "Page Attributes" panel on the right sidebar. There you will find a "Template" dropdown — select the template name from the list.', 'himalayan-homestay-bookings' ); ?>
                </div>
            </div>

            <?php /* ===================== STEP 3: SHORTCODES REFERENCE ===================== */ ?>
            <div class="hhb-section">
                <h2>
                    <span class="step-badge">3</span>
                    <?php esc_html_e( 'Shortcodes Reference', 'himalayan-homestay-bookings' ); ?>
                </h2>
                <p style="font-size:13.5px;color:#444;margin-top:0;">
                    <?php esc_html_e( 'These shortcodes can be placed in any WordPress page or post content.', 'himalayan-homestay-bookings' ); ?>
                </p>
                <table class="hhb-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Shortcode', 'himalayan-homestay-bookings' ); ?></th>
                            <th><?php esc_html_e( 'Where to Use', 'himalayan-homestay-bookings' ); ?></th>
                            <th><?php esc_html_e( 'What It Does', 'himalayan-homestay-bookings' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code class="hhb-code">[hhb_my_account]</code></td>
                            <td><?php esc_html_e( 'My Account page', 'himalayan-homestay-bookings' ); ?></td>
                            <td><?php esc_html_e( 'Shows login/register form for guests. Once logged in, displays booking history with cancellation option.', 'himalayan-homestay-bookings' ); ?></td>
                        </tr>
                        <tr>
                            <td><code class="hhb-code">[hhb_booking_form]</code></td>
                            <td><?php esc_html_e( 'Single homestay template (built-in)', 'himalayan-homestay-bookings' ); ?></td>
                            <td><?php esc_html_e( 'Date picker, room selector, guest count, availability check, price breakdown, and book button. Already embedded in the single homestay template — no manual placement needed.', 'himalayan-homestay-bookings' ); ?></td>
                        </tr>
                        <tr>
                            <td><code class="hhb-code">[hhb_data_deletion_request]</code></td>
                            <td><?php esc_html_e( 'Data Deletion page', 'himalayan-homestay-bookings' ); ?></td>
                            <td><?php esc_html_e( 'Email input form that triggers the WordPress native GDPR data erasure workflow. User must confirm via email link before deletion happens.', 'himalayan-homestay-bookings' ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php /* ===================== STEP 4: AUTO-GENERATED PAGES ===================== */ ?>
            <div class="hhb-section">
                <h2>
                    <span class="step-badge">4</span>
                    <?php esc_html_e( 'Auto-Generated Pages (No Setup Needed)', 'himalayan-homestay-bookings' ); ?>
                </h2>
                <p style="font-size:13.5px;color:#444;margin-top:0;">
                    <?php esc_html_e( 'The plugin generates these pages automatically using URL parameters — you do not need to create them in WordPress.', 'himalayan-homestay-bookings' ); ?>
                </p>
                <table class="hhb-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'URL Pattern', 'himalayan-homestay-bookings' ); ?></th>
                            <th><?php esc_html_e( 'What it Shows', 'himalayan-homestay-bookings' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code class="hhb-code">/?hhb_confirmation={booking_id}</code></td>
                            <td><?php esc_html_e( 'Booking confirmation + payment page. After a guest submits a booking, they land here to complete payment via Razorpay or PayPal. Also shows confirmed/pending status after payment.', 'himalayan-homestay-bookings' ); ?></td>
                        </tr>
                        <tr>
                            <td><code class="hhb-code">/?hhb_review=1&amp;booking_id={id}&amp;token={token}</code></td>
                            <td><?php esc_html_e( 'Guest review form. Sent to guests via email after checkout. Collects star ratings (overall, cleanliness, communication, location, value) and a comment.', 'himalayan-homestay-bookings' ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php /* ===================== STEP 5: LISTING ARCHIVES ===================== */ ?>
            <div class="hhb-section">
                <h2>
                    <span class="step-badge">5</span>
                    <?php esc_html_e( 'Listing Archive Pages (Auto-Registered)', 'himalayan-homestay-bookings' ); ?>
                </h2>
                <p style="font-size:13.5px;color:#444;margin-top:0;">
                    <?php esc_html_e( 'These URLs are created automatically by WordPress when the plugin registers its post types and taxonomies.', 'himalayan-homestay-bookings' ); ?>
                </p>
                <table class="hhb-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'URL', 'himalayan-homestay-bookings' ); ?></th>
                            <th><?php esc_html_e( 'Purpose', 'himalayan-homestay-bookings' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code class="hhb-code">/homestays/</code></td>
                            <td><?php esc_html_e( 'Archive of all published homestay listings. Supports filter params: ?location, ?type, ?guests, ?min_price, ?max_price, ?amenities', 'himalayan-homestay-bookings' ); ?></td>
                        </tr>
                        <tr>
                            <td><code class="hhb-code">/homestays/{slug}/</code></td>
                            <td><?php esc_html_e( 'Single homestay detail page with gallery, amenities, rooms, map, reviews, and booking form.', 'himalayan-homestay-bookings' ); ?></td>
                        </tr>
                        <tr>
                            <td><code class="hhb-code">/location/{slug}/</code></td>
                            <td><?php esc_html_e( 'Listings filtered by location taxonomy (Country → State → City → Area).', 'himalayan-homestay-bookings' ); ?></td>
                        </tr>
                        <tr>
                            <td><code class="hhb-code">/property-type/{slug}/</code></td>
                            <td><?php esc_html_e( 'Listings filtered by property type (villa, cottage, farmhouse, etc.).', 'himalayan-homestay-bookings' ); ?></td>
                        </tr>
                    </tbody>
                </table>
                <div class="hhb-note">
                    <?php esc_html_e( 'If these URLs return 404, go to Settings → Permalinks in WordPress and click "Save Changes" to flush rewrite rules.', 'himalayan-homestay-bookings' ); ?>
                </div>
            </div>

            <?php /* ===================== STEP 6: BOOKING FLOW ===================== */ ?>
            <div class="hhb-section">
                <h2>
                    <span class="step-badge">6</span>
                    <?php esc_html_e( 'How the Booking Flow Works', 'himalayan-homestay-bookings' ); ?>
                </h2>
                <p style="font-size:13.5px;color:#444;margin-top:0;">
                    <?php esc_html_e( 'Understanding the guest journey from browsing to confirmed booking:', 'himalayan-homestay-bookings' ); ?>
                </p>
                <div class="hhb-flow">
                    <div class="flow-box"><?php esc_html_e( '/homestays/ (browse)', 'himalayan-homestay-bookings' ); ?></div>
                    <span class="flow-arrow">→</span>
                    <div class="flow-box"><?php esc_html_e( '/homestays/{slug}/ (property detail)', 'himalayan-homestay-bookings' ); ?></div>
                    <span class="flow-arrow">→</span>
                    <div class="flow-box"><?php esc_html_e( 'Select dates, room & guests', 'himalayan-homestay-bookings' ); ?></div>
                    <span class="flow-arrow">→</span>
                    <div class="flow-box"><?php esc_html_e( 'Check availability + price', 'himalayan-homestay-bookings' ); ?></div>
                    <span class="flow-arrow">→</span>
                    <div class="flow-box"><?php esc_html_e( 'Submit booking', 'himalayan-homestay-bookings' ); ?></div>
                    <span class="flow-arrow">→</span>
                    <div class="flow-box"><?php esc_html_e( '/?hhb_confirmation={id} (pay here)', 'himalayan-homestay-bookings' ); ?></div>
                    <span class="flow-arrow">→</span>
                    <div class="flow-box" style="background:#edfaed;border-color:#c3ecc3;color:#1a5c1a;"><?php esc_html_e( 'Confirmed ✓', 'himalayan-homestay-bookings' ); ?></div>
                </div>
                <div class="hhb-tip" style="margin-top:16px;">
                    <?php esc_html_e( 'After checkout, guests automatically receive a review request email with a secure link to leave a star rating.', 'himalayan-homestay-bookings' ); ?>
                </div>
            </div>

            <?php /* ===================== STEP 7: USER ROLES ===================== */ ?>
            <div class="hhb-section">
                <h2>
                    <span class="step-badge">7</span>
                    <?php esc_html_e( 'User Roles', 'himalayan-homestay-bookings' ); ?>
                </h2>
                <table class="hhb-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Role', 'himalayan-homestay-bookings' ); ?></th>
                            <th><?php esc_html_e( 'How Assigned', 'himalayan-homestay-bookings' ); ?></th>
                            <th><?php esc_html_e( 'Permissions', 'himalayan-homestay-bookings' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code class="hhb-code">hhb_guest</code></td>
                            <td><?php esc_html_e( 'Auto-assigned when a user registers via the [hhb_my_account] form.', 'himalayan-homestay-bookings' ); ?></td>
                            <td><?php esc_html_e( 'View own bookings, cancel own bookings, submit reviews, request data deletion.', 'himalayan-homestay-bookings' ); ?></td>
                        </tr>
                        <tr>
                            <td><code class="hhb-code">hhb_host</code></td>
                            <td><?php esc_html_e( 'Manually assigned by admin after approving a Host Application.', 'himalayan-homestay-bookings' ); ?></td>
                            <td><?php esc_html_e( 'Access Host Dashboard, create/edit/delete own properties, manage room availability and pricing, view own bookings and payouts.', 'himalayan-homestay-bookings' ); ?></td>
                        </tr>
                        <tr>
                            <td><code class="hhb-code">administrator</code></td>
                            <td><?php esc_html_e( 'Standard WordPress admin role.', 'himalayan-homestay-bookings' ); ?></td>
                            <td><?php esc_html_e( 'Full access to all plugin admin pages: bookings, calendar, coupons, payouts, settings, reviews, guest users, host applications, system tools.', 'himalayan-homestay-bookings' ); ?></td>
                        </tr>
                    </tbody>
                </table>
                <div class="hhb-note">
                    <?php esc_html_e( 'To approve a host: review their application under Host Applications in this menu, then manually change their WordPress user role to "hhb_host" via Users → Edit User.', 'himalayan-homestay-bookings' ); ?>
                </div>
            </div>

            <?php /* ===================== STEP 8: CHECKLIST ===================== */ ?>
            <div class="hhb-section" style="border-color:#46b450;">
                <h2 style="color:#1a5c1a;">
                    ✅ <?php esc_html_e( 'Quick Setup Checklist', 'himalayan-homestay-bookings' ); ?>
                </h2>
                <ol class="hhb-steps">
                    <li><?php esc_html_e( 'Go to Settings and enter your Razorpay or PayPal credentials.', 'himalayan-homestay-bookings' ); ?></li>
                    <li><?php esc_html_e( 'Configure SMTP email settings so booking emails are delivered.', 'himalayan-homestay-bookings' ); ?></li>
                    <li><?php esc_html_e( 'Create a "My Account" page and add the [hhb_my_account] shortcode.', 'himalayan-homestay-bookings' ); ?></li>
                    <li><?php esc_html_e( 'Create a "Host Dashboard" page and set its template to "host-panel-template".', 'himalayan-homestay-bookings' ); ?></li>
                    <li><?php esc_html_e( 'Create a "Become a Host" page and set its template to "become-host-template".', 'himalayan-homestay-bookings' ); ?></li>
                    <li><?php esc_html_e( 'Create a Privacy Policy page and set its template to "legal-page-template".', 'himalayan-homestay-bookings' ); ?></li>
                    <li><?php esc_html_e( 'Create a "Data Deletion" page and add the [hhb_data_deletion_request] shortcode.', 'himalayan-homestay-bookings' ); ?></li>
                    <li><?php esc_html_e( 'Go to Settings → Permalinks and click Save Changes to activate /homestays/ URLs.', 'himalayan-homestay-bookings' ); ?></li>
                    <li><?php esc_html_e( 'Add your first Homestay listing under Homestay Bookings → Add New.', 'himalayan-homestay-bookings' ); ?></li>
                    <li><?php esc_html_e( 'Test the booking flow end-to-end using a test payment.', 'himalayan-homestay-bookings' ); ?></li>
                </ol>
            </div>

        </div>
        <?php
    }
}
