<?php
/**
 * System Tools Admin Page
 *
 * Provides utilities to test automated emails, PDF generation, and other background tasks.
 *
 * @package Himalayan\Homestay\Interface\Admin
 */

namespace Himalayan\Homestay\Interface\Admin;

use Himalayan\Homestay\Infrastructure\Notifications\EmailNotifier;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SystemToolsPage {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ], 70 );
    }

    public static function add_menu_page() {
        add_submenu_page(
            'edit.php?post_type=hhb_homestay',
            __( 'System Tools', 'himalayan-homestay-bookings' ),
            __( 'System Tools', 'himalayan-homestay-bookings' ),
            'manage_options',
            'hhb-system-tools',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'himalayan-homestay-bookings' ) );
        }

        $message = '';
        
        if ( isset( $_POST['hhb_run_tool'] ) && check_admin_referer( 'hhb_run_system_tool' ) ) {
            $tool = sanitize_text_field( $_POST['tool_name'] );

            if ( $tool === 'test_booking_email' ) {
                $result = self::run_test_booking_email();
                if ( $result ) {
                    $message = '<div class="notice notice-success"><p>✅ Test Booking Confirmation Email (with PDF Invoice) triggered successfully! Check your admin email or email logs.</p></div>';
                } else {
                    $message = '<div class="notice notice-error"><p>❌ Failed to trigger email or generate PDF. Make sure WP Mail is working and test properties exist.</p></div>';
                }
            }
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'System Tools & Testing', 'himalayan-homestay-bookings' ); ?></h1>
            <p>Use these utilities to verify that your automated systems (Emails, PDFs, Crons) are working correctly.</p>

            <?php echo $message; ?>

            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2 class="title">Test Automated Booking Confirmation Email</h2>
                <p>This will create a temporary "Dummy Booking" in the database and simulate a "Payment Confirmed" event. The system will generate a PDF invoice and email it to the address you provide below.</p>
                
                <form method="post" action="">
                    <?php wp_nonce_field( 'hhb_run_system_tool' ); ?>
                    <input type="hidden" name="hhb_run_tool" value="1">
                    <input type="hidden" name="tool_name" value="test_booking_email">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="test_guest_email">Test Guest Email</label></th>
                            <td>
                                <input type="email" name="test_guest_email" id="test_guest_email" class="regular-text" required placeholder="guest@example.com">
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary button-large">
                            Trigger Test Email & PDF Invoice
                        </button>
                    </p>
                </form>
            </div>
            
            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2 class="title">Email Logs</h2>
                <p>Recent automated emails sent by the system.</p>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Recipient</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        global $wpdb;
                        $logs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}himalayan_email_log ORDER BY id DESC LIMIT 10" );
                        if ( empty( $logs ) ) {
                            echo '<tr><td colspan="4">No emails logged yet.</td></tr>';
                        } else {
                            foreach ( $logs as $log ) {
                                echo '<tr>';
                                echo '<td><strong>' . esc_html( $log->email_type ) . '</strong></td>';
                                echo '<td>' . esc_html( $log->recipient ) . '<br><small>' . esc_html( $log->subject ) . '</small></td>';
                                $color = $log->status === 'sent' ? 'green' : 'red';
                                echo '<td style="color:' . $color . '; font-weight:bold;">' . esc_html( strtoupper($log->status) ) . '</td>';
                                echo '<td>' . esc_html( $log->sent_at ) . '</td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>

        </div>
        <?php
    }

    private static function run_test_booking_email(): bool {
        global $wpdb;
        
        $test_email = sanitize_email( wp_unslash( $_POST['test_guest_email'] ?? '' ) );
        if ( empty( $test_email ) ) {
            return false;
        }

        // Find a random property to attach the booking to
        $property = $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'hhb_homestay' AND post_status = 'publish' LIMIT 1" );
        if ( ! $property ) return false;

        $table = $wpdb->prefix . 'himalayan_bookings';
        
        $inserted = $wpdb->insert( $table, [
            'homestay_id'    => $property,
            'customer_name'  => 'Test System Guest',
            'customer_email' => $test_email,
            'customer_phone' => '+1234567890',
            'check_in'       => date( 'Y-m-d', strtotime( '+10 days' ) ),
            'check_out'      => date( 'Y-m-d', strtotime( '+12 days' ) ),
            'guests'         => 2,
            'total_price'    => 2500.00,
            'status'         => 'confirmed', // Mimic confirmed payment
        ] );

        if ( ! $inserted ) return false;
        
        $booking_id = $wpdb->insert_id;

        // Trigger the hook specifically. 
        // Note: EmailNotifier::on_payment_confirmed expects status to be confirmed, which we set.
        do_action( 'himalayan_payment_confirmed', $booking_id );

        return true;
    }
}
