<?php
/**
 * Guest Users Page
 *
 * Adds a custom submenu page under "Homestays" to list all
 * normal users (guests/customers) with an inline editing interface.
 *
 * @package Himalayan\Homestay\Interface\Admin
 */

namespace Himalayan\Homestay\Interface\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class GuestUsersPage {

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_submenu_page' ] );
        add_action( 'admin_post_hhb_update_guest_user', [ __CLASS__, 'handle_update' ] );
    }

    public static function add_submenu_page(): void {
        add_submenu_page(
            'edit.php?post_type=hhb_homestay',
            __( 'Guest Users', 'himalayan-homestay-bookings' ),
            __( 'Guest Users', 'himalayan-homestay-bookings' ),
            'manage_options',
            'hhb_guest_users',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Fetch all users who are NOT administrators or hosts
        // (Typically 'subscriber' or 'hhb_customer' depending on how they registered)
        $args = [
            'role__not_in' => [ 'administrator', 'hhb_host', 'editor', 'author' ],
            'orderby'      => 'registered',
            'order'        => 'DESC'
        ];
        $user_query = new \WP_User_Query( $args );
        $guests = $user_query->get_results();
        ?>
        <style>
            .hhb-apps-list {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .hhb-table-row {
                display: flex;
                align-items: center;
                border-bottom: 1px solid #f1f1f1;
                padding: 10px;
            }
            .hhb-table-row:last-child {
                border-bottom: none;
            }
            .hhb-table-row:hover {
                background: #fafafa;
            }
            .hhb-table-header {
                display: flex;
                font-weight: bold;
                padding: 12px 10px;
                background: #f1f1f1;
                border: 1px solid #ccd0d4;
                border-bottom: 2px solid #ccd0d4;
            }
            .hhb-table-col {
                padding: 0 5px;
            }
            .hhb-table-col input {
                width: 100%;
                box-sizing: border-box;
            }
        </style>

        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Guest Users (Customers)', 'himalayan-homestay-bookings' ); ?></h1>
            <p class="description">You can view and instantly update guest details inline below. Changes are saved immediately for the specific user.</p>
            <hr class="wp-header-end">

            <?php if ( isset( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'User details updated successfully.', 'himalayan-homestay-bookings' ); ?></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['deleted'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'User deleted successfully.', 'himalayan-homestay-bookings' ); ?></p></div>
            <?php endif; ?>

            <div class="hhb-apps-list" style="margin-top: 20px;">
                <div class="hhb-table-header">
                    <div style="flex: 0.5;" class="hhb-table-col">ID</div>
                    <div style="flex: 2;" class="hhb-table-col"><?php esc_html_e( 'First Name', 'himalayan-homestay-bookings' ); ?></div>
                    <div style="flex: 2;" class="hhb-table-col"><?php esc_html_e( 'Last Name', 'himalayan-homestay-bookings' ); ?></div>
                    <div style="flex: 2.5;" class="hhb-table-col"><?php esc_html_e( 'Email', 'himalayan-homestay-bookings' ); ?></div>
                    <div style="flex: 2;" class="hhb-table-col"><?php esc_html_e( 'Phone', 'himalayan-homestay-bookings' ); ?></div>
                    <div style="flex: 1.5;" class="hhb-table-col text-right"><?php esc_html_e( 'Action', 'himalayan-homestay-bookings' ); ?></div>
                </div>

                <?php if ( empty( $guests ) ) : ?>
                    <div class="hhb-table-row" style="justify-content:center; padding: 20px;">
                        <?php esc_html_e( 'No guest users found.', 'himalayan-homestay-bookings' ); ?>
                    </div>
                <?php else: ?>
                    <?php foreach ( $guests as $guest ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hhb-table-row">
                            <?php wp_nonce_field( 'hhb_update_guest_nonce', 'security' ); ?>
                            <input type="hidden" name="action" value="hhb_update_guest_user">
                            <input type="hidden" name="user_id" value="<?php echo esc_attr( $guest->ID ); ?>">
                            
                            <div style="flex: 0.5; color: #888;" class="hhb-table-col">
                                #<?php echo esc_html( $guest->ID ); ?>
                            </div>
                            <div style="flex: 2;" class="hhb-table-col">
                                <input type="text" name="first_name" value="<?php echo esc_attr( $guest->first_name ); ?>" class="regular-text">
                            </div>
                            <div style="flex: 2;" class="hhb-table-col">
                                <input type="text" name="last_name" value="<?php echo esc_attr( $guest->last_name ); ?>" class="regular-text">
                            </div>
                            <div style="flex: 2.5;" class="hhb-table-col">
                                <input type="email" name="user_email" value="<?php echo esc_attr( $guest->user_email ); ?>" required class="regular-text">
                            </div>
                            <div style="flex: 2;" class="hhb-table-col">
                                <?php $phone = get_user_meta( $guest->ID, 'billing_phone', true ) ?: get_user_meta( $guest->ID, 'phone', true ); ?>
                                <input type="text" name="user_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" placeholder="Phone">
                            </div>
                            <div style="flex: 1.5; text-align:right;" class="hhb-table-col">
                                <button type="submit" name="guest_action" value="update" class="button">
                                    Update
                                </button>
                                <button type="submit" name="guest_action" value="delete" class="button button-link-delete" onclick="return confirm('Are you sure you want to completely delete this user? This cannot be undone.');" style="margin-left:5px; color:#d63638;">
                                    Delete
                                </button>
                            </div>
                        </form>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public static function handle_update() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized.' );
        }
        if ( ! isset( $_REQUEST['security'] ) || ! wp_verify_nonce( $_REQUEST['security'], 'hhb_update_guest_nonce' ) ) {
            wp_die( 'Security check failed.' );
        }

        $user_id = intval( $_REQUEST['user_id'] );
        $user    = get_userdata( $user_id );

        if ( ! $user ) {
            wp_die( 'Invalid user.' );
        }

        $action = sanitize_text_field( $_REQUEST['guest_action'] ?? 'update' );

        if ( $action === 'delete' ) {
            require_once( ABSPATH . 'wp-admin/includes/user.php' );
            wp_delete_user( $user_id );
            wp_redirect( admin_url( 'edit.php?post_type=hhb_homestay&page=hhb_guest_users&deleted=1' ) );
            exit;
        }

        $first_name = sanitize_text_field( $_REQUEST['first_name'] ?? '' );
        $last_name  = sanitize_text_field( $_REQUEST['last_name'] ?? '' );
        $email      = sanitize_email( $_REQUEST['user_email'] ?? '' );
        $phone      = sanitize_text_field( $_REQUEST['user_phone'] ?? '' );

        if ( empty( $email ) ) {
            wp_die( 'Email is required.' );
        }

        // Check if email belongs to someone else
        $existing = get_user_by( 'email', $email );
        if ( $existing && $existing->ID !== $user_id ) {
            wp_die( 'That email is already in use by another user.' );
        }

        wp_update_user([
            'ID'           => $user_id,
            'user_email'   => $email,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => trim( $first_name . ' ' . $last_name ) ?: $user->display_name
        ]);

        update_user_meta( $user_id, 'billing_phone', $phone );
        update_user_meta( $user_id, 'phone', $phone );

        wp_redirect( admin_url( 'edit.php?post_type=hhb_homestay&page=hhb_guest_users&updated=1' ) );
        exit;
    }
}
