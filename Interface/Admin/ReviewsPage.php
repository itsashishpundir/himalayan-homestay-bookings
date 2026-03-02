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

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'himalayan-homestay-bookings' ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'hhb_reviews';

        // Handle deletion if requested
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
            check_admin_referer( 'delete_review_' . intval( $_GET['id'] ) );
            $id = intval( $_GET['id'] );
            $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Review deleted successfully.', 'himalayan-homestay-bookings' ) . '</p></div>';
        }

        // Fetch all reviews
        $reviews = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );
        ?>
        <div class="wrap hhb-admin-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Guest Reviews', 'himalayan-homestay-bookings' ); ?></h1>
            <hr class="wp-header-end">

            <div class="hhb-apps-list" style="margin-top: 20px;">
                <table class="wp-list-table widefat fixed striped table-view-list">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column column-primary" style="width: 20%;">Property</th>
                            <th scope="col" class="manage-column">Guest</th>
                            <th scope="col" class="manage-column" style="width: 10%;">Score</th>
                            <th scope="col" class="manage-column" style="width: 35%;">Comment</th>
                            <th scope="col" class="manage-column">Date</th>
                            <th scope="col" class="manage-column" style="width: 80px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $reviews ) ) : ?>
                            <tr>
                                <td colspan="6"><?php esc_html_e( 'No reviews found.', 'himalayan-homestay-bookings' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $reviews as $r ) : ?>
                                <tr>
                                    <td class="column-primary">
                                        <strong><?php echo esc_html( get_the_title( $r['homestay_id'] ) ); ?></strong>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html( $r['customer_name'] ); ?></strong><br>
                                        <a href="mailto:<?php echo esc_attr( $r['customer_email'] ); ?>"><?php echo esc_html( $r['customer_email'] ); ?></a>
                                    </td>
                                    <td>
                                        <div style="color: #f5b301; font-size: 16px;">
                                            <?php echo str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo esc_html( $r['comment'] ); ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html( wp_date( get_option('date_format'), strtotime( $r['created_at'] ) ) ); ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $delete_url = wp_nonce_url( 
                                            admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-reviews&action=delete&id=' . $r['id'] ), 
                                            'delete_review_' . $r['id'] 
                                        ); 
                                        ?>
                                        <a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small" style="color: #b32d2e; border-color: #b32d2e;" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this review? This action cannot be undone.', 'himalayan-homestay-bookings' ); ?>');">
                                            <?php esc_html_e( 'Delete', 'himalayan-homestay-bookings' ); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
