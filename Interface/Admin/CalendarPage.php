<?php
/**
 * Calendar Admin Page
 *
 * Renders a visual availability calendar in the WordPress admin, showing
 * all bookings across all homestays in a month-view grid. Admins can add
 * manual blocks (e.g., maintenance) and see at-a-glance occupancy.
 *
 * @package Himalayan\Homestay\Interface\Admin
 */

namespace Himalayan\Homestay\Interface\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CalendarPage {

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_ajax_hhb_calendar_data', [ __CLASS__, 'ajax_calendar_data' ] );
        add_action( 'wp_ajax_hhb_add_block', [ __CLASS__, 'ajax_add_block' ] );
    }

    public static function add_menu_page(): void {
        add_submenu_page(
            'edit.php?post_type=hhb_homestay',
            __( 'Availability Calendar', 'himalayan-homestay-bookings' ),
            __( 'Calendar', 'himalayan-homestay-bookings' ),
            'manage_options',
            'hhb-calendar',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function enqueue_assets( string $hook ): void {
        if ( 'hhb_homestay_page_hhb-calendar' !== $hook ) return;

        wp_enqueue_style( 'hhb-calendar-css', HHB_PLUGIN_URL . 'assets/calendar.css', [], HHB_VERSION );
        wp_enqueue_script( 'hhb-calendar-js', HHB_PLUGIN_URL . 'assets/calendar.js', [ 'jquery' ], HHB_VERSION, true );
        wp_localize_script( 'hhb-calendar-js', 'hhbCalendar', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'hhb_calendar_nonce' ),
        ]);
    }

    // -------------------------------------------------------------------------
    // AJAX: Fetch bookings for a given month.
    // -------------------------------------------------------------------------

    public static function ajax_calendar_data(): void {
        check_ajax_referer( 'hhb_calendar_nonce', 'nonce' );

        $year  = intval( $_POST['year']  ?? date('Y') );
        $month = intval( $_POST['month'] ?? date('m') );

        $start_date = sprintf( '%04d-%02d-01', $year, $month );
        $end_date   = date( 'Y-m-t', strtotime( $start_date ) );

        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_bookings';

        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT b.id, b.homestay_id, b.customer_name, b.check_in, b.check_out, b.status,
                    p.post_title as homestay_title
             FROM {$table} b
             LEFT JOIN {$wpdb->posts} p ON b.homestay_id = p.ID
             WHERE b.status IN ('pending_inquiry','approved','confirmed')
             AND b.check_in <= %s AND b.check_out >= %s
             ORDER BY b.check_in",
            $end_date, $start_date
        ), ARRAY_A );

        wp_send_json_success([
            'bookings' => $bookings,
            'year'     => $year,
            'month'    => $month,
        ]);
    }

    // -------------------------------------------------------------------------
    // AJAX: Add a manual block.
    // -------------------------------------------------------------------------

    public static function ajax_add_block(): void {
        check_ajax_referer( 'hhb_calendar_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $homestay_id = intval( $_POST['homestay_id'] ?? 0 );
        $check_in    = sanitize_text_field( $_POST['check_in'] ?? '' );
        $check_out   = sanitize_text_field( $_POST['check_out'] ?? '' );
        $reason      = sanitize_text_field( $_POST['reason'] ?? 'Manual Block' );

        if ( ! $homestay_id || ! $check_in || ! $check_out ) {
            wp_send_json_error( 'Missing required fields.' );
        }

        global $wpdb;
        $inserted = $wpdb->insert( $wpdb->prefix . 'himalayan_bookings', [
            'homestay_id'    => $homestay_id,
            'customer_name'  => $reason,
            'customer_email' => 'block@internal',
            'check_in'       => $check_in,
            'check_out'      => $check_out,
            'total_price'    => 0,
            'status'         => 'confirmed',
            'notes'          => 'Manual block by admin.',
        ]);

        wp_send_json_success( [ 'id' => $wpdb->insert_id ] );
    }

    // -------------------------------------------------------------------------
    // Page Render
    // -------------------------------------------------------------------------

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions.', 'himalayan-homestay-bookings' ) );
        }

        // Get all homestays for the filter dropdown.
        $homestays = get_posts([
            'post_type'      => 'hhb_homestay',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php esc_html_e( 'Availability Calendar', 'himalayan-homestay-bookings' ); ?>
            </h1>
            <hr class="wp-header-end">

            <div id="hhb-calendar-app">
                <!-- Toolbar -->
                <div class="hhb-cal-toolbar">
                    <button type="button" class="button" id="hhb-cal-prev">&larr; <?php esc_html_e( 'Prev', 'himalayan-homestay-bookings' ); ?></button>
                    <h2 id="hhb-cal-title"></h2>
                    <button type="button" class="button" id="hhb-cal-next"><?php esc_html_e( 'Next', 'himalayan-homestay-bookings' ); ?> &rarr;</button>
                    <button type="button" class="button" id="hhb-cal-today"><?php esc_html_e( 'Today', 'himalayan-homestay-bookings' ); ?></button>
                </div>

                <!-- Legend -->
                <div class="hhb-cal-legend">
                    <span class="hhb-legend-item"><span class="hhb-dot hhb-dot-pending"></span> <?php esc_html_e( 'Pending', 'himalayan-homestay-bookings' ); ?></span>
                    <span class="hhb-legend-item"><span class="hhb-dot hhb-dot-approved"></span> <?php esc_html_e( 'Approved', 'himalayan-homestay-bookings' ); ?></span>
                    <span class="hhb-legend-item"><span class="hhb-dot hhb-dot-confirmed"></span> <?php esc_html_e( 'Confirmed', 'himalayan-homestay-bookings' ); ?></span>
                </div>

                <!-- Calendar Grid -->
                <div id="hhb-cal-grid" class="hhb-cal-grid">
                    <div class="hhb-cal-loading"><?php esc_html_e( 'Loading...', 'himalayan-homestay-bookings' ); ?></div>
                </div>

                <!-- Block Modal -->
                <div id="hhb-block-modal" class="hhb-modal" style="display:none">
                    <div class="hhb-modal-content">
                        <h3><?php esc_html_e( 'Block Dates', 'himalayan-homestay-bookings' ); ?></h3>
                        <div class="hhb-modal-field">
                            <label><?php esc_html_e( 'Property', 'himalayan-homestay-bookings' ); ?></label>
                            <select id="hhb-block-homestay">
                                <?php foreach ( $homestays as $hs ) : ?>
                                    <option value="<?php echo esc_attr( $hs->ID ); ?>">
                                        <?php echo esc_html( $hs->post_title ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="hhb-modal-field">
                            <label><?php esc_html_e( 'From', 'himalayan-homestay-bookings' ); ?></label>
                            <input type="date" id="hhb-block-from">
                        </div>
                        <div class="hhb-modal-field">
                            <label><?php esc_html_e( 'To', 'himalayan-homestay-bookings' ); ?></label>
                            <input type="date" id="hhb-block-to">
                        </div>
                        <div class="hhb-modal-field">
                            <label><?php esc_html_e( 'Reason', 'himalayan-homestay-bookings' ); ?></label>
                            <input type="text" id="hhb-block-reason" placeholder="e.g. Maintenance, Owner Use">
                        </div>
                        <div class="hhb-modal-actions">
                            <button type="button" class="button button-primary" id="hhb-block-save"><?php esc_html_e( 'Block Dates', 'himalayan-homestay-bookings' ); ?></button>
                            <button type="button" class="button" id="hhb-block-cancel"><?php esc_html_e( 'Cancel', 'himalayan-homestay-bookings' ); ?></button>
                        </div>
                    </div>
                </div>

                <p style="margin-top:12px">
                    <button type="button" class="button button-secondary" id="hhb-add-block-btn">
                        + <?php esc_html_e( 'Block Dates Manually', 'himalayan-homestay-bookings' ); ?>
                    </button>
                </p>
            </div>
        </div>
        <?php
    }
}
