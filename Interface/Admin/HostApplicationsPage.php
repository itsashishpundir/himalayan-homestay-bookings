<?php
/**
 * Host Applications Admin Page (Custom Table)
 *
 * Provides a clean HTML table to manage Host Applications
 * without directing the admin to the WordPress Post Editor.
 *
 * @package Himalayan\Homestay\Interface\Admin
 */

namespace Himalayan\Homestay\Interface\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class HostApplicationsPage {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ] );
        add_action( 'admin_post_hhb_update_host_app', [ __CLASS__, 'handle_update' ] );
    }

    public static function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=hhb_homestay',
            __( 'Host Applications', 'himalayan-homestay-bookings' ),
            __( 'Host Apps', 'himalayan-homestay-bookings' ),
            'manage_options',
            'hhb_host_apps',
            [ __CLASS__, 'render_admin_page' ]
        );
    }

    public static function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }

        // Fetch applications
        $applications = get_posts([
            'post_type'      => 'hhb_host_app',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        ?>
        <style>
            .hhb-table-row {
                display: flex;
                align-items: center;
                border: 1px solid #ccd0d4;
                border-top: none;
                padding: 10px;
                background: #fff;
                margin-bottom: 0px;
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
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Host Applications', 'himalayan-homestay-bookings' ); ?></h1>
            <p class="description">You can edit the Name, Email, Phone, and Password directly inline below.</p>
            <hr class="wp-header-end">

            <?php if ( isset( $_GET['approved'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Application approved and Host account created successfully.', 'himalayan-homestay-bookings' ); ?></p></div>
            <?php elseif ( isset( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Host Application details updated successfully.', 'himalayan-homestay-bookings' ); ?></p></div>
            <?php endif; ?>

            <div class="hhb-apps-list" style="margin-top: 20px;">
                <div class="hhb-table-header">
                    <div style="flex: 2;" class="hhb-table-col"><?php esc_html_e( 'Name', 'himalayan-homestay-bookings' ); ?></div>
                    <div style="flex: 2;" class="hhb-table-col"><?php esc_html_e( 'Email', 'himalayan-homestay-bookings' ); ?></div>
                    <div style="flex: 1.5;" class="hhb-table-col"><?php esc_html_e( 'Phone', 'himalayan-homestay-bookings' ); ?></div>
                    <div style="flex: 1.5;" class="hhb-table-col"><?php esc_html_e( 'Password', 'himalayan-homestay-bookings' ); ?></div>
                    <div style="flex: 1;" class="hhb-table-col text-center"><?php esc_html_e( 'Status', 'himalayan-homestay-bookings' ); ?></div>
                    <div style="flex: 1;" class="hhb-table-col text-right"><?php esc_html_e( 'Action', 'himalayan-homestay-bookings' ); ?></div>
                </div>

                <?php if ( empty( $applications ) ) : ?>
                    <div class="hhb-table-row" style="justify-content:center; padding: 20px;">
                        <?php esc_html_e( 'No applications found.', 'himalayan-homestay-bookings' ); ?>
                    </div>
                <?php else: ?>
                    <?php foreach ( $applications as $app ) : 
                        $name   = get_post_meta( $app->ID, 'host_name', true ) ?: 'Unknown';
                        $email  = get_post_meta( $app->ID, 'host_email', true );
                        $phone  = get_post_meta( $app->ID, 'host_phone', true );
                        $status = get_post_meta( $app->ID, 'hhb_app_status', true ) ?: 'pending';
                        $initial_pwd = get_post_meta( $app->ID, 'initial_password', true );
                        
                        if ( empty( $initial_pwd ) ) {
                            $initial_pwd = wp_generate_password( 12, false );
                        }
                    ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hhb-table-row">
                            <?php wp_nonce_field( 'hhb_update_host_app_nonce', 'security' ); ?>
                            <input type="hidden" name="action" value="hhb_update_host_app">
                            <input type="hidden" name="post_id" value="<?php echo esc_attr( $app->ID ); ?>">
                            
                            <div style="flex: 2;" class="hhb-table-col">
                                <input type="text" name="host_name" value="<?php echo esc_attr( $name ); ?>" required class="regular-text">
                            </div>
                            <div style="flex: 2;" class="hhb-table-col">
                                <input type="email" name="host_email" value="<?php echo esc_attr( $email ); ?>" required class="regular-text">
                            </div>
                            <div style="flex: 1.5;" class="hhb-table-col">
                                <input type="text" name="host_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text">
                            </div>
                            <div style="flex: 1.5;" class="hhb-table-col">
                                <input type="text" name="host_password" value="<?php echo esc_attr( $initial_pwd ); ?>" required class="regular-text">
                            </div>
                            <div style="flex: 1;" class="hhb-table-col">
                                <?php if ( $status === 'approved' ) : ?>
                                    <span style="display:inline-block; padding:4px 8px; background:#e5faea; color:#0e8b39; border-radius:4px; font-weight:600; font-size:12px;">Approved</span>
                                <?php else : ?>
                                    <span style="display:inline-block; padding:4px 8px; background:#fff8e5; color:#b1750e; border-radius:4px; font-weight:600; font-size:12px;">Pending</span>
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1; text-align:right;" class="hhb-table-col">
                                <?php if ( $status !== 'approved' ) : ?>
                                    <button type="submit" name="app_action" value="approve" class="button button-primary" onclick="return confirm('Are you sure you want to approve this application and create the user account?');">
                                        Approve
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="app_action" value="update" class="button">
                                        Update
                                    </button>
                                <?php endif; ?>
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
        if ( ! isset( $_REQUEST['security'] ) || ! wp_verify_nonce( $_REQUEST['security'], 'hhb_update_host_app_nonce' ) ) {
            wp_die( 'Security check failed.' );
        }

        $post_id = intval( $_REQUEST['post_id'] );
        $post    = get_post( $post_id );

        if ( ! $post || $post->post_type !== 'hhb_host_app' ) {
            wp_die( 'Invalid application.' );
        }

        $name   = sanitize_text_field( $_REQUEST['host_name'] );
        $email  = sanitize_email( $_REQUEST['host_email'] );
        $phone  = sanitize_text_field( $_REQUEST['host_phone'] );
        $pwd    = sanitize_text_field( str_replace(' ', '', $_REQUEST['host_password']) );
        $action = isset( $_REQUEST['app_action'] ) ? sanitize_text_field( $_REQUEST['app_action'] ) : 'update';

        if ( empty( $email ) || empty( $pwd ) ) {
            wp_die( 'Cannot process: Email and Password are required.' );
        }

        // Update post meta fields right away
        update_post_meta( $post_id, 'host_name', $name );
        update_post_meta( $post_id, 'host_email', $email );
        update_post_meta( $post_id, 'host_phone', $phone );

        $status  = get_post_meta( $post_id, 'hhb_app_status', true ) ?: 'pending';
        $old_pwd = get_post_meta( $post_id, 'initial_password', true );

        if ( $action === 'approve' && $status !== 'approved' ) {
            // Check if user already exists
            $user = get_user_by( 'email', $email );
            $user_id = 0;

            if ( ! $user ) {
                $user_id  = wp_create_user( $email, $pwd, $email );
                if ( is_wp_error( $user_id ) ) {
                    wp_die( 'Error creating user: ' . $user_id->get_error_message() );
                }

                $new_user = new \WP_User( $user_id );
                $new_user->set_role( 'hhb_host' );
                
                $name_parts = explode(' ', $name, 2);
                wp_update_user([
                    'ID' => $user_id,
                    'first_name' => $name_parts[0] ?? '',
                    'last_name'  => $name_parts[1] ?? '',
                    'display_name' => $name
                ]);
            } else {
                $user->add_role( 'hhb_host' );
                wp_set_password( $pwd, $user->ID );
                $user_id = $user->ID;
            }

            update_post_meta( $post_id, 'hhb_app_status', 'approved' );
            update_post_meta( $post_id, 'approved_user_id', $user_id );
            update_post_meta( $post_id, 'initial_password', $pwd );

            wp_redirect( admin_url( 'edit.php?post_type=hhb_homestay&page=hhb_host_apps&approved=1' ) );
            exit;

        } elseif ( $action === 'update' && $status === 'approved' ) {
            // Save the newly typed password in meta
            update_post_meta( $post_id, 'initial_password', $pwd );
            
            // Sync changes to WP User
            $user_id = get_post_meta( $post_id, 'approved_user_id', true );
            
            // Fallback user find if meta is missing
            if ( ! $user_id ) {
                $user = get_user_by( 'email', $email );
                if ( $user ) $user_id = $user->ID;
            }

            if ( $user_id ) {
                if ( $old_pwd !== $pwd ) {
                    wp_set_password( $pwd, $user_id );
                }

                $name_parts = explode(' ', $name, 2);
                $update_args = [
                    'ID'           => $user_id,
                    'user_email'   => $email,
                    'first_name'   => $name_parts[0] ?? '',
                    'last_name'    => $name_parts[1] ?? '',
                    'display_name' => $name
                ];
                wp_update_user( $update_args );
            }

            wp_redirect( admin_url( 'edit.php?post_type=hhb_homestay&page=hhb_host_apps&updated=1' ) );
            exit;
        }

        // Catch-all redirect
        wp_redirect( admin_url( 'edit.php?post_type=hhb_homestay&page=hhb_host_apps&updated=1' ) );
        exit;
    }
}
