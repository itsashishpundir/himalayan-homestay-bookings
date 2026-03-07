<?php
/**
 * Admin Reviews Management Page
 *
 * Displays all submitted reviews in an WP_List_Table style interface.
 *
 * @package Himalayan\Homestay\Interface\Admin
 */

namespace Himalayan\Homestay\Interface\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ReviewsPage {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ], 60 );
    }

    public static function add_menu_page() {
        add_submenu_page(
            'edit.php?post_type=hhb_homestay',
            __( 'Reviews', 'himalayan-homestay-bookings' ),
            __( 'Reviews', 'himalayan-homestay-bookings' ),
            'manage_options',
            'hhb-reviews',
            [ __CLASS__, 'render_page' ]
        );
    }

    private static function base_url(): string {
        return admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-reviews' );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'himalayan-homestay-bookings' ) );
        }

        global $wpdb;
        $table      = $wpdb->prefix . 'hhb_reviews';
        $base_url   = self::base_url();
        $notice     = '';
        $edit_data  = null; // pre-fill edit form

        // ── Handle GET actions ────────────────────────────────────────────────
        $get_action = sanitize_key( $_GET['action'] ?? '' );
        $get_id     = intval( $_GET['id'] ?? 0 );

        if ( $get_id && in_array( $get_action, [ 'delete', 'approve', 'unapprove', 'edit' ], true ) ) {
            check_admin_referer( 'hhb_review_' . $get_action . '_' . $get_id );

            if ( $get_action === 'delete' ) {
                $wpdb->delete( $table, [ 'id' => $get_id ], [ '%d' ] );
                delete_transient( 'hhb_reviews_' . self::get_homestay_id_for_review( $get_id ) );
                $notice = '<div class="notice notice-success is-dismissible"><p>Review deleted.</p></div>';

            } elseif ( $get_action === 'approve' ) {
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT homestay_id FROM {$table} WHERE id = %d", $get_id ) );
                $wpdb->update( $table, [ 'status' => 'approved' ], [ 'id' => $get_id ], [ '%s' ], [ '%d' ] );
                if ( $row ) delete_transient( 'hhb_reviews_' . $row->homestay_id );
                $notice = '<div class="notice notice-success is-dismissible"><p>Review approved.</p></div>';

            } elseif ( $get_action === 'unapprove' ) {
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT homestay_id FROM {$table} WHERE id = %d", $get_id ) );
                $wpdb->update( $table, [ 'status' => 'pending' ], [ 'id' => $get_id ], [ '%s' ], [ '%d' ] );
                if ( $row ) delete_transient( 'hhb_reviews_' . $row->homestay_id );
                $notice = '<div class="notice notice-warning is-dismissible"><p>Review set to pending.</p></div>';

            } elseif ( $get_action === 'edit' ) {
                $edit_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $get_id ), ARRAY_A );
            }
        }

        // ── Handle POST: Add or Update review ────────────────────────────────
        if ( isset( $_POST['hhb_review_form_nonce'] ) ) {
            $post_action = sanitize_key( $_POST['hhb_review_action'] ?? 'add' );
            $edit_id     = intval( $_POST['edit_id'] ?? 0 );
            $nonce_key   = $post_action === 'edit' ? 'hhb_admin_edit_review_' . $edit_id : 'hhb_admin_add_review';

            if ( ! wp_verify_nonce( $_POST['hhb_review_form_nonce'], $nonce_key ) ) {
                $notice = '<div class="notice notice-error"><p>Security check failed.</p></div>';
            } else {
                $homestay_id   = intval( $_POST['homestay_id'] ?? 0 );
                $customer_name = sanitize_text_field( wp_unslash( $_POST['customer_name'] ?? '' ) );
                $customer_email= sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) );
                $rating        = max( 1, min( 5, intval( $_POST['rating'] ?? 5 ) ) );
                $r_clean       = max( 1, min( 5, intval( $_POST['rating_cleanliness']   ?? 5 ) ) );
                $r_comm        = max( 1, min( 5, intval( $_POST['rating_communication'] ?? 5 ) ) );
                $r_loc         = max( 1, min( 5, intval( $_POST['rating_location']      ?? 5 ) ) );
                $r_val         = max( 1, min( 5, intval( $_POST['rating_value']         ?? 5 ) ) );
                $comment       = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );
                $status        = in_array( $_POST['status'] ?? '', [ 'approved', 'pending' ], true ) ? $_POST['status'] : 'approved';

                if ( ! $homestay_id || ! $customer_name || ! $comment ) {
                    $notice = '<div class="notice notice-error"><p>Property, Guest Name and Comment are required.</p></div>';
                } else {
                    $row_data = [
                        'homestay_id'          => $homestay_id,
                        'customer_name'        => $customer_name,
                        'customer_email'       => $customer_email,
                        'rating'               => $rating,
                        'rating_cleanliness'   => $r_clean,
                        'rating_communication' => $r_comm,
                        'rating_location'      => $r_loc,
                        'rating_value'         => $r_val,
                        'comment'              => $comment,
                        'status'               => $status,
                    ];

                    if ( $post_action === 'edit' && $edit_id ) {
                        $wpdb->update( $table, $row_data, [ 'id' => $edit_id ], null, [ '%d' ] );
                        delete_transient( 'hhb_reviews_' . $homestay_id );
                        $notice    = '<div class="notice notice-success is-dismissible"><p>Review updated successfully.</p></div>';
                        $edit_data = null; // close form after save
                    } else {
                        $row_data['booking_id']  = 0; // admin-added, no booking
                        $row_data['created_at']  = current_time( 'mysql' );
                        $wpdb->insert( $table, $row_data );
                        delete_transient( 'hhb_reviews_' . $homestay_id );
                        $notice = '<div class="notice notice-success is-dismissible"><p>Review added successfully.</p></div>';
                    }
                }
            }
        }

        // ── Fetch all reviews ─────────────────────────────────────────────────
        $reviews    = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );
        $homestays  = get_posts( [ 'post_type' => 'hhb_homestay', 'posts_per_page' => -1, 'post_status' => [ 'publish', 'draft', 'pending' ], 'orderby' => 'title', 'order' => 'ASC' ] );
        $show_form  = isset( $_GET['add'] ) || $edit_data;
        $form_title = $edit_data ? 'Edit Review' : 'Add Review Manually';
        $form_nonce = $edit_data ? 'hhb_admin_edit_review_' . $edit_data['id'] : 'hhb_admin_add_review';
        $form_action_val = $edit_data ? 'edit' : 'add';
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Guest Reviews', 'himalayan-homestay-bookings' ); ?></h1>
            <a href="<?php echo esc_url( add_query_arg( 'add', '1', $base_url ) ); ?>" class="page-title-action">+ Add Review</a>
            <hr class="wp-header-end">

            <?php echo $notice; ?>

            <?php if ( $show_form ) : ?>
            <!-- ── ADD / EDIT FORM ── -->
            <div style="background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:24px; margin:20px 0; max-width:760px;">
                <h2 style="margin-top:0;"><?php echo esc_html( $form_title ); ?></h2>
                <form method="post" action="<?php echo esc_url( $base_url ); ?>">
                    <?php wp_nonce_field( $form_nonce, 'hhb_review_form_nonce' ); ?>
                    <input type="hidden" name="hhb_review_action" value="<?php echo esc_attr( $form_action_val ); ?>">
                    <?php if ( $edit_data ) : ?>
                        <input type="hidden" name="edit_id" value="<?php echo esc_attr( $edit_data['id'] ); ?>">
                    <?php endif; ?>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th><label for="hhb-homestay-id">Property <span style="color:red">*</span></label></th>
                            <td>
                                <select name="homestay_id" id="hhb-homestay-id" required style="min-width:300px;">
                                    <option value="">— Select Property —</option>
                                    <?php foreach ( $homestays as $h ) : ?>
                                        <option value="<?php echo esc_attr( $h->ID ); ?>" <?php selected( $edit_data['homestay_id'] ?? 0, $h->ID ); ?>>
                                            <?php echo esc_html( $h->post_title ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="hhb-cname">Guest Name <span style="color:red">*</span></label></th>
                            <td><input type="text" id="hhb-cname" name="customer_name" value="<?php echo esc_attr( $edit_data['customer_name'] ?? '' ); ?>" class="regular-text" required placeholder="e.g. Ravi Kumar"></td>
                        </tr>
                        <tr>
                            <th><label for="hhb-cemail">Guest Email</label></th>
                            <td><input type="email" id="hhb-cemail" name="customer_email" value="<?php echo esc_attr( $edit_data['customer_email'] ?? '' ); ?>" class="regular-text" placeholder="optional"></td>
                        </tr>
                        <tr>
                            <th>Overall Rating <span style="color:red">*</span></th>
                            <td>
                                <div style="display:flex; gap:6px; align-items:center;">
                                    <?php for ( $s = 1; $s <= 5; $s++ ) : ?>
                                        <label style="cursor:pointer; font-size:28px; color:#ccc;" title="<?php echo $s; ?> star">
                                            <input type="radio" name="rating" value="<?php echo $s; ?>" <?php checked( intval( $edit_data['rating'] ?? 5 ), $s ); ?> required style="display:none;" onchange="hhbSyncStars(this)">
                                            <span class="hhb-star" data-val="<?php echo $s; ?>">★</span>
                                        </label>
                                    <?php endfor; ?>
                                    <span id="hhb-star-count" style="font-size:13px; color:#666; margin-left:6px;"></span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>Sub-Ratings</th>
                            <td>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                                    <?php
                                    $sub = [
                                        'rating_cleanliness'   => 'Cleanliness',
                                        'rating_communication' => 'Communication',
                                        'rating_location'      => 'Location',
                                        'rating_value'         => 'Value',
                                    ];
                                    foreach ( $sub as $key => $label ) : ?>
                                    <div>
                                        <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px; color:#444;"><?php echo esc_html( $label ); ?></label>
                                        <select name="<?php echo esc_attr( $key ); ?>" style="width:100%;">
                                            <?php for ( $s = 5; $s >= 1; $s-- ) : ?>
                                                <option value="<?php echo $s; ?>" <?php selected( intval( $edit_data[ $key ] ?? 5 ), $s ); ?>><?php echo $s; ?> Star<?php echo $s > 1 ? 's' : ''; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="hhb-comment">Review <span style="color:red">*</span></label></th>
                            <td><textarea id="hhb-comment" name="comment" rows="5" class="large-text" required placeholder="Write the review text here…"><?php echo esc_textarea( $edit_data['comment'] ?? '' ); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="hhb-status">Status</label></th>
                            <td>
                                <select name="status" id="hhb-status">
                                    <option value="approved" <?php selected( $edit_data['status'] ?? 'approved', 'approved' ); ?>>Approved (Visible)</option>
                                    <option value="pending"  <?php selected( $edit_data['status'] ?? '', 'pending' ); ?>>Pending (Hidden)</option>
                                </select>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <?php submit_button( $edit_data ? 'Update Review' : 'Add Review', 'primary', 'submit', false ); ?>
                        <a href="<?php echo esc_url( $base_url ); ?>" class="button" style="margin-left:8px;">Cancel</a>
                    </p>
                </form>
            </div>

            <script>
            function hhbSyncStars(radio) {
                var val = parseInt(radio.value);
                document.querySelectorAll('.hhb-star').forEach(function(s) {
                    s.style.color = parseInt(s.dataset.val) <= val ? '#f5b301' : '#ccc';
                });
                var labels = ['','Poor','Fair','Good','Very Good','Excellent'];
                document.getElementById('hhb-star-count').textContent = val + '/5 — ' + labels[val];
            }
            // Init stars on page load for edit mode.
            (function() {
                var checked = document.querySelector('input[name="rating"]:checked');
                if (checked) hhbSyncStars(checked);
            })();
            </script>
            <?php endif; ?>

            <!-- ── REVIEWS TABLE ── -->
            <table class="wp-list-table widefat fixed striped" style="margin-top:<?php echo $show_form ? '0' : '20px'; ?>">
                <thead>
                    <tr>
                        <th style="width:18%">Property</th>
                        <th style="width:16%">Guest</th>
                        <th style="width:9%">Rating</th>
                        <th style="width:9%">Status</th>
                        <th>Comment</th>
                        <th style="width:9%">Date</th>
                        <th style="width:160px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $reviews ) ) : ?>
                        <tr><td colspan="7" style="text-align:center; padding:24px; color:#666;">No reviews yet.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $reviews as $r ) :
                            $r_id       = (int) $r['id'];
                            $is_approved= $r['status'] === 'approved';
                            $edit_url   = wp_nonce_url( add_query_arg( [ 'action' => 'edit',     'id' => $r_id ], $base_url ), 'hhb_review_edit_' . $r_id );
                            $del_url    = wp_nonce_url( add_query_arg( [ 'action' => 'delete',   'id' => $r_id ], $base_url ), 'hhb_review_delete_' . $r_id );
                            $toggle_url = $is_approved
                                ? wp_nonce_url( add_query_arg( [ 'action' => 'unapprove', 'id' => $r_id ], $base_url ), 'hhb_review_unapprove_' . $r_id )
                                : wp_nonce_url( add_query_arg( [ 'action' => 'approve',   'id' => $r_id ], $base_url ), 'hhb_review_approve_' . $r_id );
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( get_the_title( $r['homestay_id'] ) ?: '(ID ' . $r['homestay_id'] . ')' ); ?></strong></td>
                            <td>
                                <strong><?php echo esc_html( $r['customer_name'] ); ?></strong>
                                <?php if ( $r['customer_email'] ) : ?>
                                    <br><span style="font-size:11px; color:#666;"><?php echo esc_html( $r['customer_email'] ); ?></span>
                                <?php endif; ?>
                                <?php if ( ! $r['booking_id'] ) : ?>
                                    <br><span style="font-size:10px; background:#e0e7ff; color:#3730a3; padding:1px 6px; border-radius:10px; font-weight:700;">Admin Added</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#f5b301; font-size:15px; letter-spacing:-1px;">
                                <?php echo str_repeat( '★', (int) $r['rating'] ) . str_repeat( '☆', 5 - (int) $r['rating'] ); ?>
                            </td>
                            <td>
                                <?php if ( $is_approved ) : ?>
                                    <span style="background:#dcfce7; color:#166534; font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px;">Approved</span>
                                <?php else : ?>
                                    <span style="background:#fef9c3; color:#854d0e; font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px;">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:13px; color:#444;"><?php echo esc_html( wp_trim_words( $r['comment'], 20, '…' ) ); ?></td>
                            <td style="font-size:12px; color:#666;"><?php echo esc_html( wp_date( 'd M Y', strtotime( $r['created_at'] ) ) ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">Edit</a>
                                <a href="<?php echo esc_url( $toggle_url ); ?>" class="button button-small" style="margin:2px 0;"><?php echo $is_approved ? 'Unapprove' : 'Approve'; ?></a>
                                <a href="<?php echo esc_url( $del_url ); ?>" class="button button-small" style="color:#b32d2e; border-color:#b32d2e;" onclick="return confirm('Delete this review? This cannot be undone.');">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function get_homestay_id_for_review( int $review_id ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'hhb_reviews';
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT homestay_id FROM {$table} WHERE id = %d", $review_id ) );
    }
}
