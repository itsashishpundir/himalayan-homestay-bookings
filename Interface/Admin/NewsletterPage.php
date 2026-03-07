<?php
/**
 * Admin Newsletter Page
 *
 * Subscriber list + one-click campaign sender.
 *
 * @package Himalayan\Homestay\Interface\Admin
 */

namespace Himalayan\Homestay\Interface\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class NewsletterPage {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ], 65 );
    }

    public static function add_menu_page() {
        add_submenu_page(
            'edit.php?post_type=hhb_homestay',
            __( 'Newsletter', 'himalayan-homestay-bookings' ),
            __( 'Newsletter', 'himalayan-homestay-bookings' ),
            'manage_options',
            'hhb-newsletter',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page() {
        // Handle delete subscriber action
        if ( isset( $_GET['action'], $_GET['sub_id'], $_GET['_wpnonce'] )
            && $_GET['action'] === 'delete'
            && wp_verify_nonce( $_GET['_wpnonce'], 'hhb_delete_subscriber' )
            && current_user_can( 'manage_options' )
        ) {
            \Himalayan\Homestay\Infrastructure\Newsletter\NewsletterManager::delete_subscriber( (int) $_GET['sub_id'] );
            echo '<div class="notice notice-success is-dismissible"><p>Subscriber deleted.</p></div>';
        }

        $per_page     = 30;
        $current_page = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $subscribers  = \Himalayan\Homestay\Infrastructure\Newsletter\NewsletterManager::get_all_subscribers( $per_page, $current_page );
        $total        = \Himalayan\Homestay\Infrastructure\Newsletter\NewsletterManager::get_total_subscribers();
        $active_count = \Himalayan\Homestay\Infrastructure\Newsletter\NewsletterManager::get_subscriber_count();
        $total_pages  = max( 1, ceil( $total / $per_page ) );
        $nonce        = wp_create_nonce( 'hhb_newsletter_admin' );
        ?>
        <div class="wrap" id="hhb-newsletter-admin">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <span class="dashicons dashicons-email-alt" style="font-size:28px;width:28px;height:28px;color:#e85e30;"></span>
            Newsletter
        </h1>

        <style>
        #hhb-newsletter-admin { max-width:1100px; }
        .hhbn-stats-row { display:flex;gap:16px;margin:20px 0; }
        .hhbn-stat-card { background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px 28px;min-width:140px;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.06); }
        .hhbn-stat-num  { font-size:36px;font-weight:800;color:#e85e30;line-height:1; }
        .hhbn-stat-lbl  { font-size:12px;color:#666;margin-top:4px; }
        .hhbn-panel     { background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:28px 32px;margin-bottom:24px;box-shadow:0 1px 4px rgba(0,0,0,.06); }
        .hhbn-panel h2  { margin:0 0 20px;font-size:17px;font-weight:700; }
        .hhbn-field     { margin-bottom:16px; }
        .hhbn-field label { display:block;font-size:13px;font-weight:600;margin-bottom:5px; }
        .hhbn-field input[type=text] { width:100%;padding:10px 12px;border:1px solid #ccc;border-radius:6px;font-size:14px; }
        .hhbn-field textarea { width:100%;padding:10px 12px;border:1px solid #ccc;border-radius:6px;font-size:14px;min-height:180px;resize:vertical; }
        .hhbn-send-btn { background:#e85e30;color:#fff;border:none;padding:12px 28px;border-radius:7px;font-size:15px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;transition:background .2s; }
        .hhbn-send-btn:hover { background:#c94e20; }
        .hhbn-send-btn:disabled { background:#aaa;cursor:not-allowed; }
        #hhbn-campaign-result { margin-top:14px;padding:12px 16px;border-radius:7px;font-size:14px;display:none; }
        #hhbn-campaign-result.success { background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7; }
        #hhbn-campaign-result.error   { background:#ffebee;color:#b71c1c;border:1px solid #ef9a9a; }
        .hhbn-table { width:100%;border-collapse:collapse;font-size:14px; }
        .hhbn-table th { background:#f5f5f5;padding:10px 12px;text-align:left;font-weight:600;border-bottom:2px solid #e0e0e0; }
        .hhbn-table td { padding:9px 12px;border-bottom:1px solid #f0f0f0;vertical-align:middle; }
        .hhbn-table tr:hover td { background:#fafafa; }
        .hhbn-badge { display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase; }
        .hhbn-badge.active { background:#e8f5e9;color:#2e7d32; }
        .hhbn-badge.unsubscribed { background:#fce4ec;color:#b71c1c; }
        .hhbn-del-btn { color:#c0392b;text-decoration:none;font-size:12px; }
        .hhbn-del-btn:hover { text-decoration:underline; }
        .hhbn-pagination { margin-top:16px;display:flex;gap:6px;align-items:center; }
        .hhbn-pagination a, .hhbn-pagination span { padding:5px 12px;border:1px solid #ddd;border-radius:5px;font-size:13px;text-decoration:none;color:#333; }
        .hhbn-pagination .current { background:#e85e30;color:#fff;border-color:#e85e30; }
        </style>

        <!-- Stats Row -->
        <div class="hhbn-stats-row">
            <div class="hhbn-stat-card">
                <div class="hhbn-stat-num"><?php echo esc_html( $active_count ); ?></div>
                <div class="hhbn-stat-lbl">Active Subscribers</div>
            </div>
            <div class="hhbn-stat-card">
                <div class="hhbn-stat-num"><?php echo esc_html( $total ); ?></div>
                <div class="hhbn-stat-lbl">Total (all time)</div>
            </div>
        </div>

        <!-- Campaign Sender -->
        <div class="hhbn-panel">
            <h2><span class="dashicons dashicons-megaphone" style="color:#e85e30;margin-right:6px;"></span>Send Campaign Email</h2>
            <p style="margin:0 0 18px;color:#666;font-size:13px;">
                Compose your campaign below and click Send. The email will be delivered to all <strong><?php echo esc_html( $active_count ); ?></strong> active subscribers. Each email includes a personalised unsubscribe link.
            </p>

            <div class="hhbn-field">
                <label for="hhbn-subject">Subject Line</label>
                <input type="text" id="hhbn-subject" placeholder="e.g. Discover Hidden Himalayan Gems This Summer">
            </div>
            <div class="hhbn-field">
                <label for="hhbn-body">Email Body <span style="font-weight:400;color:#888;">(HTML supported)</span></label>
                <textarea id="hhbn-body" placeholder="Write your message here. You can use &lt;p&gt;, &lt;b&gt;, &lt;a href&gt;, etc."></textarea>
            </div>

            <button class="hhbn-send-btn" id="hhbn-send-btn">
                <span class="dashicons dashicons-email" style="margin-top:3px;"></span>
                Send to <?php echo esc_html( $active_count ); ?> Subscribers
            </button>

            <div id="hhbn-campaign-result"></div>
        </div>

        <!-- Subscriber List -->
        <div class="hhbn-panel">
            <h2><span class="dashicons dashicons-groups" style="color:#e85e30;margin-right:6px;"></span>Subscriber List</h2>

            <?php if ( empty( $subscribers ) ) : ?>
                <p style="color:#888;">No subscribers yet.</p>
            <?php else : ?>
            <table class="hhbn-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Subscribed</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $subscribers as $i => $sub ) :
                    $del_url = wp_nonce_url(
                        add_query_arg( [ 'action' => 'delete', 'sub_id' => $sub->id ], admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-newsletter' ) ),
                        'hhb_delete_subscriber'
                    );
                ?>
                    <tr>
                        <td style="color:#aaa;"><?php echo esc_html( ( $current_page - 1 ) * $per_page + $i + 1 ); ?></td>
                        <td><strong><?php echo esc_html( $sub->email ); ?></strong></td>
                        <td><?php echo esc_html( $sub->name ?: '—' ); ?></td>
                        <td>
                            <span class="hhbn-badge <?php echo esc_attr( $sub->status ); ?>">
                                <?php echo esc_html( $sub->status ); ?>
                            </span>
                        </td>
                        <td style="color:#666;font-size:13px;">
                            <?php echo esc_html( date_i18n( 'M j, Y', strtotime( $sub->subscribed_at ) ) ); ?>
                        </td>
                        <td>
                            <a class="hhbn-del-btn" href="<?php echo esc_url( $del_url ); ?>"
                               onclick="return confirm('Delete this subscriber?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ( $total_pages > 1 ) : ?>
            <div class="hhbn-pagination">
                <?php for ( $p = 1; $p <= $total_pages; $p++ ) :
                    $url = add_query_arg( 'paged', $p );
                ?>
                    <?php if ( $p === $current_page ) : ?>
                        <span class="current"><?php echo $p; ?></span>
                    <?php else : ?>
                        <a href="<?php echo esc_url( $url ); ?>"><?php echo $p; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <span style="color:#888;font-size:12px;margin-left:8px;"><?php echo esc_html( $total ); ?> total</span>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        </div><!-- .wrap -->

        <script>
        (function(){
            const btn    = document.getElementById('hhbn-send-btn');
            const result = document.getElementById('hhbn-campaign-result');

            btn.addEventListener('click', function() {
                const subject = document.getElementById('hhbn-subject').value.trim();
                const body    = document.getElementById('hhbn-body').value.trim();

                if ( ! subject || ! body ) {
                    result.className = 'error';
                    result.style.display = 'block';
                    result.textContent = 'Please fill in both subject and body.';
                    return;
                }

                btn.disabled = true;
                btn.innerHTML = '<span class="dashicons dashicons-update" style="margin-top:3px;animation:spin 1s linear infinite;"></span> Sending…';

                const data = new FormData();
                data.append('action',  'hhb_newsletter_send_campaign');
                data.append('nonce',   '<?php echo esc_js( $nonce ); ?>');
                data.append('subject', subject);
                data.append('body',    body);

                fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                })
                .then( r => r.json() )
                .then( res => {
                    result.style.display = 'block';
                    if ( res.success ) {
                        result.className = 'success';
                        result.textContent = res.data.message;
                        document.getElementById('hhbn-subject').value = '';
                        document.getElementById('hhbn-body').value    = '';
                    } else {
                        result.className = 'error';
                        result.textContent = res.data.message || 'Something went wrong.';
                    }
                })
                .catch( () => {
                    result.className = 'error';
                    result.style.display = 'block';
                    result.textContent = 'Network error. Please try again.';
                })
                .finally( () => {
                    btn.disabled = false;
                    btn.innerHTML = '<span class="dashicons dashicons-email" style="margin-top:3px;"></span> Send to <?php echo esc_js( $active_count ); ?> Subscribers';
                });
            });

            // Spin animation for loading icon
            const style = document.createElement('style');
            style.textContent = '@keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }';
            document.head.appendChild(style);
        })();
        </script>
        <?php
    }
}
