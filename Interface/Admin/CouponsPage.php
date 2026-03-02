<?php
/**
 * Coupons Admin Page
 *
 * Renders a CRUD interface in the WordPress admin to manage discount coupons.
 *
 * @package Himalayan\Homestay\Interface\Admin
 */

namespace Himalayan\Homestay\Interface\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CouponsPage {

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
        add_action( 'admin_init', [ __CLASS__, 'handle_form_submission' ] );
    }

    public static function add_menu_page(): void {
        add_submenu_page(
            'edit.php?post_type=hhb_homestay',
            __( 'Discount Coupons', 'himalayan-homestay-bookings' ),
            __( 'Coupons', 'himalayan-homestay-bookings' ),
            'manage_options',
            'hhb-coupons',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function handle_form_submission(): void {
        if ( ! isset( $_POST['hhb_coupon_nonce'] ) || ! wp_verify_nonce( $_POST['hhb_coupon_nonce'], 'hhb_save_coupon' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_coupons';

        if ( isset( $_POST['action'] ) && $_POST['action'] === 'add_coupon' ) {
            $code = sanitize_text_field( $_POST['code'] ?? '' );
            $type = sanitize_text_field( $_POST['discount_type'] ?? 'percent' );
            $val  = floatval( $_POST['discount_value'] ?? 0 );
            $max  = intval( $_POST['max_uses'] ?? 0 );
            $from = ! empty( $_POST['valid_from'] ) ? sanitize_text_field( $_POST['valid_from'] ) . ' 00:00:00' : null;
            $to   = ! empty( $_POST['valid_to'] ) ? sanitize_text_field( $_POST['valid_to'] ) . ' 23:59:59' : null;

            if ( $code && $val > 0 ) {
                $wpdb->insert( $table, [
                    'code'           => strtoupper( $code ),
                    'discount_type'  => $type,
                    'discount_value' => $val,
                    'max_uses'       => $max,
                    'valid_from'     => $from,
                    'valid_to'       => $to,
                    'is_active'      => 1
                ] );
            }
        } elseif ( isset( $_POST['action'] ) && $_POST['action'] === 'delete_coupon' ) {
            $id = intval( $_POST['coupon_id'] ?? 0 );
            if ( $id ) {
                $wpdb->delete( $table, [ 'id' => $id ] );
            }
        }
        
        // Redirect to clear POST
        wp_redirect( admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-coupons' ) );
        exit;
    }

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions.', 'himalayan-homestay-bookings' ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_coupons';
        
        // Suppress errors briefly in case the db upgrade hasn't refreshed
        $suppress = $wpdb->suppress_errors();
        $coupons = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
        $wpdb->suppress_errors( $suppress );
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php esc_html_e( 'Discount Coupons', 'himalayan-homestay-bookings' ); ?>
            </h1>
            <hr class="wp-header-end">

            <div style="display:flex; gap: 20px; align-items: flex-start; margin-top: 20px;">
                
                <div style="flex:2;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Discount</th>
                                <th>Usage</th>
                                <th>Valid Window</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $coupons ) ) : foreach ( $coupons as $c ) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $c->code ); ?></strong></td>
                                    <td>
                                        <?php 
                                        if ( $c->discount_type === 'percent' ) {
                                            echo esc_html( $c->discount_value . '%' );
                                        } else {
                                            echo esc_html( '₹' . $c->discount_value );
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html( $c->used_count ); ?> / 
                                        <?php echo $c->max_uses > 0 ? esc_html( $c->max_uses ) : '&infin;'; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ( $c->valid_from || $c->valid_to ) {
                                            $f = $c->valid_from ? gmdate( 'M j, Y', strtotime($c->valid_from) ) : 'Always';
                                            $t = $c->valid_to ? gmdate( 'M j, Y', strtotime($c->valid_to) ) : 'Forever';
                                            echo esc_html( $f . ' - ' . $t );
                                        } else {
                                            echo 'Always Valid';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo $c->is_active ? '✅ Active' : '❌ Inactive'; ?></td>
                                    <td>
                                        <form method="post" action="" style="display:inline;">
                                            <?php wp_nonce_field( 'hhb_save_coupon', 'hhb_coupon_nonce' ); ?>
                                            <input type="hidden" name="action" value="delete_coupon">
                                            <input type="hidden" name="coupon_id" value="<?php echo esc_attr( $c->id ); ?>">
                                            <button type="submit" class="button button-small" onclick="return confirm('Delete this coupon?');">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr><td colspan="6">No coupons found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="flex:1; background:#fff; padding:20px; border:1px solid #ccd0d4; box-shadow:0 1px 1px rgba(0,0,0,.04);">
                    <h2 style="margin-top:0;">Add New Coupon</h2>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'hhb_save_coupon', 'hhb_coupon_nonce' ); ?>
                        <input type="hidden" name="action" value="add_coupon">
                        
                        <p>
                            <label style="display:block; font-weight:bold; margin-bottom:5px;">Coupon Code</label>
                            <input type="text" name="code" required style="width:100%; text-transform:uppercase;" placeholder="e.g. SUMMER25">
                        </p>
                        
                        <div style="display:flex; gap:10px;">
                            <p style="flex:1;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px;">Type</label>
                                <select name="discount_type" style="width:100%;">
                                    <option value="percent">Percentage (%)</option>
                                    <option value="fixed">Fixed Amount (₹)</option>
                                </select>
                            </p>
                            <p style="flex:1;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px;">Value</label>
                                <input type="number" name="discount_value" required min="1" step="0.01" style="width:100%;">
                            </p>
                        </div>

                        <p>
                            <label style="display:block; font-weight:bold; margin-bottom:5px;">Max Uses (0 = unlimited)</label>
                            <input type="number" name="max_uses" value="0" min="0" style="width:100%;">
                        </p>

                        <div style="display:flex; gap:10px;">
                            <p style="flex:1;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px;">Valid From</label>
                                <input type="date" name="valid_from" style="width:100%;">
                            </p>
                            <p style="flex:1;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px;">Valid To</label>
                                <input type="date" name="valid_to" style="width:100%;">
                            </p>
                        </div>

                        <p style="margin-top:20px;">
                            <button type="submit" class="button button-primary button-large" style="width:100%;">Create Coupon</button>
                        </p>
                    </form>
                </div>

            </div>
        </div>
        <?php
    }
}
