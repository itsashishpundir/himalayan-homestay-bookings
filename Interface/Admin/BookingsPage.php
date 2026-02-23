<?php
/**
 * Bookings Admin Page
 *
 * Renders the Bookings management page in the WordPress admin, using the
 * native WP_List_Table for a fully integrated, professional experience.
 *
 * @package Himalayan\Homestay\Interface\Admin
 */

namespace Himalayan\Homestay\Interface\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// ============================================================================
// LIST TABLE CLASS
// ============================================================================

/**
 * Bookings_List_Table
 *
 * Extends WP_List_Table to display bookings in a native WordPress admin table.
 */
class Bookings_List_Table extends \WP_List_Table {

    /** @var string Currently filtered status. */
    private string $current_status = '';

    public function __construct() {
        parent::__construct( [
            'singular' => 'booking',
            'plural'   => 'bookings',
            'ajax'     => false,
        ] );

        $this->current_status = isset( $_REQUEST['status'] ) ? sanitize_key( $_REQUEST['status'] ) : '';
    }

    // -------------------------------------------------------------------------
    // Column Definitions
    // -------------------------------------------------------------------------

    public function get_columns(): array {
        return [
            'cb'         => '<input type="checkbox" />',
            'id'         => __( 'ID', 'himalayan-homestay-bookings' ),
            'homestay'   => __( 'Homestay', 'himalayan-homestay-bookings' ),
            'customer'   => __( 'Customer', 'himalayan-homestay-bookings' ),
            'dates'      => __( 'Dates', 'himalayan-homestay-bookings' ),
            'amount'     => __( 'Amount', 'himalayan-homestay-bookings' ),
            'status'     => __( 'Status', 'himalayan-homestay-bookings' ),
            'created_at' => __( 'Date Created', 'himalayan-homestay-bookings' ),
        ];
    }

    public function get_sortable_columns(): array {
        return [
            'id'         => [ 'id', true ],
            'amount'     => [ 'total_price', false ],
            'status'     => [ 'status', false ],
            'created_at' => [ 'created_at', true ],
        ];
    }

    public function get_bulk_actions(): array {
        return [
            'bulk_delete' => __( 'Delete', 'himalayan-homestay-bookings' ),
        ];
    }

    // -------------------------------------------------------------------------
    // Status View Filters (tabs at top of table)
    // -------------------------------------------------------------------------

    public function get_views(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_bookings';

        $all_count = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table}" );

        $statuses = [
            'pending_inquiry' => __( 'Pending', 'himalayan-homestay-bookings' ),
            'approved'        => __( 'Approved', 'himalayan-homestay-bookings' ),
            'confirmed'       => __( 'Confirmed', 'himalayan-homestay-bookings' ),
            'cancelled'       => __( 'Cancelled', 'himalayan-homestay-bookings' ),
        ];

        $base_url = admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-bookings' );

        $views = [
            'all' => sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url( $base_url ),
                empty( $this->current_status ) ? ' class="current" aria-current="page"' : '',
                __( 'All', 'himalayan-homestay-bookings' ),
                $all_count
            ),
        ];

        foreach ( $statuses as $key => $label ) {
            $count = (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(id) FROM {$table} WHERE status = %s", $key )
            );
            if ( $count > 0 ) {
                $views[ $key ] = sprintf(
                    '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                    esc_url( add_query_arg( 'status', $key, $base_url ) ),
                    $this->current_status === $key ? ' class="current" aria-current="page"' : '',
                    esc_html( $label ),
                    $count
                );
            }
        }

        return $views;
    }

    // -------------------------------------------------------------------------
    // Column Renderers
    // -------------------------------------------------------------------------

    public function column_default( $item, $column_name ): string {
        switch ( $column_name ) {
            case 'id':
                return '<strong>#' . esc_html( $item['id'] ) . '</strong>';
            case 'amount':
                return '<strong>$' . esc_html( number_format( (float) $item['total_price'], 2 ) ) . '</strong>';
            case 'status':
                return $this->render_status_badge( $item['status'] );
            case 'created_at':
                $ts = strtotime( $item['created_at'] );
                return sprintf(
                    '<abbr title="%s">%s</abbr>',
                    esc_attr( date_i18n( 'Y-m-d H:i:s', $ts ) ),
                    esc_html( date_i18n( get_option( 'date_format' ), $ts ) )
                );
            default:
                return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '—';
        }
    }

    public function column_cb( $item ): string {
        return sprintf( '<input type="checkbox" name="booking[]" value="%d" />', intval( $item['id'] ) );
    }

    public function column_homestay( $item ): string {
        $homestay = get_post( $item['homestay_id'] );
        $title    = $homestay ? $homestay->post_title : __( '(Deleted Property)', 'himalayan-homestay-bookings' );

        $delete_url = wp_nonce_url(
            add_query_arg( [ 'action' => 'delete', 'booking' => $item['id'] ], admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-bookings' ) ),
            'hhb_delete_booking_' . $item['id']
        );

        $actions = [
            'view' => sprintf(
                '<a href="%s">%s</a>',
                esc_url( add_query_arg( [ 'action' => 'view', 'booking' => $item['id'] ], admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-bookings' ) ) ),
                __( 'View', 'himalayan-homestay-bookings' )
            ),
            'delete' => sprintf(
                '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\')">%s</a>',
                esc_url( $delete_url ),
                esc_js( __( 'Are you sure you want to permanently delete this booking?', 'himalayan-homestay-bookings' ) ),
                __( 'Delete', 'himalayan-homestay-bookings' )
            ),
        ];

        if ( 'pending_inquiry' === $item['status'] ) {
            $approve_url        = wp_nonce_url(
                add_query_arg( [ 'action' => 'approve', 'booking' => $item['id'] ], admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-bookings' ) ),
                'hhb_approve_booking_' . $item['id']
            );
            $actions['approve'] = sprintf(
                '<a href="%s" style="color: #2e7d32; font-weight: 600;">%s</a>',
                esc_url( $approve_url ),
                __( 'Approve', 'himalayan-homestay-bookings' )
            );
        }

        $edit_link = $homestay ? get_edit_post_link( $item['homestay_id'] ) : '';
        $title_html = $edit_link
            ? sprintf( '<a href="%s">%s</a>', esc_url( $edit_link ), esc_html( $title ) )
            : esc_html( $title );

        return sprintf( '<strong>%s</strong>%s', $title_html, $this->row_actions( $actions ) );
    }

    public function column_customer( $item ): string {
        $gravatar = get_avatar_url( $item['customer_email'], [ 'size' => 32, 'default' => 'mystery' ] );
        return sprintf(
            '<div style="display:flex;align-items:center;gap:8px">'
            . '<img src="%s" width="32" height="32" style="border-radius:50%%;flex-shrink:0" />'
            . '<div><strong>%s</strong><br>'
            . '<a href="mailto:%s">%s</a><br>'
            . '<span style="color:#666">%s</span>'
            . '</div></div>',
            esc_url( $gravatar ),
            esc_html( $item['customer_name'] ),
            esc_attr( $item['customer_email'] ),
            esc_html( $item['customer_email'] ),
            esc_html( $item['customer_phone'] )
        );
    }

    public function column_dates( $item ): string {
        $fmt      = get_option( 'date_format' );
        $check_in  = date_i18n( $fmt, strtotime( $item['check_in'] ) );
        $check_out = date_i18n( $fmt, strtotime( $item['check_out'] ) );
        $nights   = (int) ( ( strtotime( $item['check_out'] ) - strtotime( $item['check_in'] ) ) / DAY_IN_SECONDS );

        return sprintf(
            '<strong>%s</strong> &rarr; <strong>%s</strong><br><span style="color:#666">%s</span>',
            esc_html( $check_in ),
            esc_html( $check_out ),
            sprintf(
                /* translators: %d: number of nights */
                _n( '%d night', '%d nights', $nights, 'himalayan-homestay-bookings' ),
                $nights
            )
        );
    }

    // -------------------------------------------------------------------------
    // Helper: Status Badge
    // -------------------------------------------------------------------------

    private function render_status_badge( string $status ): string {
        $label = ucwords( str_replace( '_', ' ', $status ) );
        return sprintf(
            '<span class="hhb-status status-%s">%s</span>',
            esc_attr( sanitize_html_class( $status ) ),
            esc_html( $label )
        );
    }

    // -------------------------------------------------------------------------
    // Action Processing
    // -------------------------------------------------------------------------

    public function process_bulk_action(): void {
        global $wpdb;
        $table    = $wpdb->prefix . 'himalayan_bookings';
        $redirect = admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-bookings' );

        // Single delete (with nonce).
        if ( 'delete' === $this->current_action() && isset( $_REQUEST['booking'] ) ) {
            $id = intval( $_REQUEST['booking'] );
            if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'hhb_delete_booking_' . $id ) ) {
                wp_die( __( 'Security check failed.', 'himalayan-homestay-bookings' ) );
            }
            $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
            wp_redirect( add_query_arg( 'deleted', 1, $redirect ) );
            exit;
        }

        // Single approve (with nonce).
        if ( 'approve' === $this->current_action() && isset( $_REQUEST['booking'] ) ) {
            $id = intval( $_REQUEST['booking'] );
            if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'hhb_approve_booking_' . $id ) ) {
                wp_die( __( 'Security check failed.', 'himalayan-homestay-bookings' ) );
            }
            $wpdb->update( $table, [ 'status' => 'approved' ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );
            wp_redirect( add_query_arg( 'approved', 1, $redirect ) );
            exit;
        }

        // Bulk delete.
        if ( 'bulk_delete' === $this->current_action() && ! empty( $_REQUEST['booking'] ) ) {
            check_admin_referer( 'bulk-bookings' );
            $ids = array_map( 'intval', (array) $_REQUEST['booking'] );
            foreach ( $ids as $id ) {
                $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
            }
            wp_redirect( add_query_arg( 'deleted', count( $ids ), $redirect ) );
            exit;
        }
    }

    // -------------------------------------------------------------------------
    // Data Preparation
    // -------------------------------------------------------------------------

    public function prepare_items(): void {
        global $wpdb;
        $table    = $wpdb->prefix . 'himalayan_bookings';
        $per_page = 20;

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
            'homestay', // Primary column for responsive.
        ];

        $this->process_bulk_action();

        // Build WHERE clause for status filter.
        $where = '';
        if ( ! empty( $this->current_status ) ) {
            $where = $wpdb->prepare( ' WHERE status = %s', $this->current_status );
        }

        // Validate ORDER BY to prevent SQL injection.
        $allowed_columns = [ 'id', 'total_price', 'status', 'created_at' ];
        $orderby         = isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $allowed_columns, true )
            ? sanitize_key( $_GET['orderby'] )
            : 'id';
        $order           = ( isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ) ? 'ASC' : 'DESC';

        $total_items = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table}{$where}" );
        $paged       = $this->get_pagenum();
        $offset      = ( $paged - 1 ) * $per_page;

        $query       = "SELECT * FROM {$table}{$where} ORDER BY {$orderby} {$order} LIMIT %d, %d";
        $this->items = $wpdb->get_results(
            $wpdb->prepare( $query, $offset, $per_page ),
            ARRAY_A
        );

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil( $total_items / $per_page ),
        ] );
    }
}


// ============================================================================
// PAGE CONTROLLER CLASS
// ============================================================================

/**
 * BookingsPage
 *
 * Registers the Bookings admin page, enqueues styles, and handles rendering
 * of both the list table view and the single-booking detail view.
 */
class BookingsPage {

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_styles' ] );
    }

    public static function add_menu_page(): void {
        add_submenu_page(
            'edit.php?post_type=hhb_homestay',
            __( 'Homestay Bookings', 'himalayan-homestay-bookings' ),
            __( 'Bookings', 'himalayan-homestay-bookings' ),
            'manage_options',
            'hhb-bookings',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function enqueue_styles( string $hook ): void {
        if ( 'hhb_homestay_page_hhb-bookings' !== $hook ) {
            return;
        }

        $css = '
            /* ---- Status Badges ---- */
            .hhb-status {
                display: inline-block;
                padding: 3px 10px;
                border-radius: 20px;
                font-weight: 600;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                line-height: 1.6;
            }
            .status-pending_inquiry { background: #fff8e1; color: #e65100; border: 1px solid #ffcc80; }
            .status-approved        { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }
            .status-confirmed       { background: #e3f2fd; color: #0d47a1; border: 1px solid #90caf9; }
            .status-cancelled       { background: #ffebee; color: #b71c1c; border: 1px solid #ef9a9a; }

            /* ---- Detail View ---- */
            .hhb-detail-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin: 20px 0;
            }
            .hhb-detail-card {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 6px;
                padding: 20px 24px;
            }
            .hhb-detail-card h3 {
                margin: 0 0 14px;
                font-size: 13px;
                text-transform: uppercase;
                color: #757575;
                letter-spacing: 0.5px;
                border-bottom: 1px solid #f0f0f0;
                padding-bottom: 10px;
            }
            .hhb-detail-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 10px;
                font-size: 13px;
            }
            .hhb-detail-row .label { color: #666; }
            .hhb-detail-row .value { font-weight: 600; color: #111; text-align: right; }
            .hhb-back-btn { margin-bottom: 16px !important; }
            .hhb-notice { padding: 8px 14px; border-radius: 4px; margin-bottom: 16px; display: inline-block; }
            .hhb-notice-success { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }
        ';
        wp_add_inline_style( 'wp-admin', $css );
    }

    // -------------------------------------------------------------------------
    // Page Rendering
    // -------------------------------------------------------------------------

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'himalayan-homestay-bookings' ) );
        }

        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

        if ( 'view' === $action && isset( $_GET['booking'] ) ) {
            self::render_detail_view( intval( $_GET['booking'] ) );
        } else {
            self::render_list_view();
        }
    }

    // -------------------------------------------------------------------------
    // List View
    // -------------------------------------------------------------------------

    private static function render_list_view(): void {
        $list_table = new Bookings_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php esc_html_e( 'Homestay Bookings', 'himalayan-homestay-bookings' ); ?>
            </h1>
            <hr class="wp-header-end">

            <?php if ( ! empty( $_GET['deleted'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        $count = intval( $_GET['deleted'] );
                        printf(
                            /* translators: %d: number of deleted bookings */
                            _n(
                                '%d booking permanently deleted.',
                                '%d bookings permanently deleted.',
                                $count,
                                'himalayan-homestay-bookings'
                            ),
                            $count
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $_GET['approved'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Booking approved successfully.', 'himalayan-homestay-bookings' ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post">
                <?php
                $list_table->views();
                $list_table->search_box( __( 'Search Bookings', 'himalayan-homestay-bookings' ), 'booking' );
                $list_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Single Booking Detail View
    // -------------------------------------------------------------------------

    private static function render_detail_view( int $booking_id ): void {
        global $wpdb;
        $table   = $wpdb->prefix . 'himalayan_bookings';
        $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $booking_id ), ARRAY_A );

        if ( ! $booking ) {
            wp_die( __( 'Booking not found.', 'himalayan-homestay-bookings' ) );
        }

        $back_url   = admin_url( 'edit.php?post_type=hhb_homestay&page=hhb-bookings' );
        $homestay   = get_post( $booking['homestay_id'] );
        $hs_title   = $homestay ? $homestay->post_title : __( '(Deleted Property)', 'himalayan-homestay-bookings' );
        $fmt        = get_option( 'date_format' );
        $check_in   = date_i18n( $fmt, strtotime( $booking['check_in'] ) );
        $check_out  = date_i18n( $fmt, strtotime( $booking['check_out'] ) );
        $nights     = (int) ( ( strtotime( $booking['check_out'] ) - strtotime( $booking['check_in'] ) ) / DAY_IN_SECONDS );
        $created    = date_i18n( $fmt . ' ' . get_option( 'time_format' ), strtotime( $booking['created_at'] ) );

        $approve_url = wp_nonce_url(
            add_query_arg( [ 'action' => 'approve', 'booking' => $booking_id ], $back_url ),
            'hhb_approve_booking_' . $booking_id
        );
        $delete_url  = wp_nonce_url(
            add_query_arg( [ 'action' => 'delete', 'booking' => $booking_id ], $back_url ),
            'hhb_delete_booking_' . $booking_id
        );
        ?>
        <div class="wrap">
            <h1>
                <?php
                printf(
                    /* translators: %d: booking ID */
                    esc_html__( 'Booking #%d', 'himalayan-homestay-bookings' ),
                    $booking_id
                );
                ?>
            </h1>

            <a href="<?php echo esc_url( $back_url ); ?>" class="button hhb-back-btn">
                &larr; <?php esc_html_e( 'Back to Bookings', 'himalayan-homestay-bookings' ); ?>
            </a>

            <?php if ( 'pending_inquiry' === $booking['status'] ) : ?>
                <a href="<?php echo esc_url( $approve_url ); ?>" class="button button-primary">
                    ✓ <?php esc_html_e( 'Approve Booking', 'himalayan-homestay-bookings' ); ?>
                </a>
            <?php endif; ?>

            <a href="<?php echo esc_url( $delete_url ); ?>"
               class="button button-link-delete"
               style="margin-left:8px"
               onclick="return confirm('<?php echo esc_js( __( 'Permanently delete this booking? This cannot be undone.', 'himalayan-homestay-bookings' ) ); ?>')">
                <?php esc_html_e( 'Delete Booking', 'himalayan-homestay-bookings' ); ?>
            </a>

            <hr class="wp-header-end">

            <div class="hhb-detail-grid">

                <div class="hhb-detail-card">
                    <h3><?php esc_html_e( 'Property', 'himalayan-homestay-bookings' ); ?></h3>
                    <div class="hhb-detail-row">
                        <span class="label"><?php esc_html_e( 'Name', 'himalayan-homestay-bookings' ); ?></span>
                        <span class="value">
                            <?php if ( $homestay ) : ?>
                                <a href="<?php echo esc_url( get_edit_post_link( $homestay->ID ) ); ?>">
                                    <?php echo esc_html( $hs_title ); ?>
                                </a>
                            <?php else : ?>
                                <?php echo esc_html( $hs_title ); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <div class="hhb-detail-card">
                    <h3><?php esc_html_e( 'Customer', 'himalayan-homestay-bookings' ); ?></h3>
                    <div class="hhb-detail-row">
                        <span class="label"><?php esc_html_e( 'Name', 'himalayan-homestay-bookings' ); ?></span>
                        <span class="value"><?php echo esc_html( $booking['customer_name'] ); ?></span>
                    </div>
                    <div class="hhb-detail-row">
                        <span class="label"><?php esc_html_e( 'Email', 'himalayan-homestay-bookings' ); ?></span>
                        <span class="value">
                            <a href="mailto:<?php echo esc_attr( $booking['customer_email'] ); ?>">
                                <?php echo esc_html( $booking['customer_email'] ); ?>
                            </a>
                        </span>
                    </div>
                    <div class="hhb-detail-row">
                        <span class="label"><?php esc_html_e( 'Phone', 'himalayan-homestay-bookings' ); ?></span>
                        <span class="value"><?php echo esc_html( $booking['customer_phone'] ); ?></span>
                    </div>
                </div>

                <div class="hhb-detail-card">
                    <h3><?php esc_html_e( 'Booking Dates', 'himalayan-homestay-bookings' ); ?></h3>
                    <div class="hhb-detail-row">
                        <span class="label"><?php esc_html_e( 'Check-In', 'himalayan-homestay-bookings' ); ?></span>
                        <span class="value"><?php echo esc_html( $check_in ); ?></span>
                    </div>
                    <div class="hhb-detail-row">
                        <span class="label"><?php esc_html_e( 'Check-Out', 'himalayan-homestay-bookings' ); ?></span>
                        <span class="value"><?php echo esc_html( $check_out ); ?></span>
                    </div>
                    <div class="hhb-detail-row">
                        <span class="label"><?php esc_html_e( 'Duration', 'himalayan-homestay-bookings' ); ?></span>
                        <span class="value">
                            <?php echo esc_html( sprintf(
                                _n( '%d night', '%d nights', $nights, 'himalayan-homestay-bookings' ),
                                $nights
                            ) ); ?>
                        </span>
                    </div>
                </div>

                <div class="hhb-detail-card">
                    <h3><?php esc_html_e( 'Payment', 'himalayan-homestay-bookings' ); ?></h3>
                    <div class="hhb-detail-row">
                        <span class="label"><?php esc_html_e( 'Total Amount', 'himalayan-homestay-bookings' ); ?></span>
                        <span class="value" style="font-size:16px">
                            $<?php echo esc_html( number_format( (float) $booking['total_price'], 2 ) ); ?>
                        </span>
                    </div>
                    <div class="hhb-detail-row">
                        <span class="label"><?php esc_html_e( 'Status', 'himalayan-homestay-bookings' ); ?></span>
                        <span class="value">
                            <?php
                            $label = ucwords( str_replace( '_', ' ', $booking['status'] ) );
                            echo sprintf(
                                '<span class="hhb-status status-%s">%s</span>',
                                esc_attr( sanitize_html_class( $booking['status'] ) ),
                                esc_html( $label )
                            );
                            ?>
                        </span>
                    </div>
                    <div class="hhb-detail-row">
                        <span class="label"><?php esc_html_e( 'Requested On', 'himalayan-homestay-bookings' ); ?></span>
                        <span class="value"><?php echo esc_html( $created ); ?></span>
                    </div>
                </div>

            </div><!-- .hhb-detail-grid -->

            <?php if ( ! empty( $booking['notes'] ) ) : ?>
            <div class="hhb-detail-card" style="margin-top:0">
                <h3><?php esc_html_e( 'Customer Notes', 'himalayan-homestay-bookings' ); ?></h3>
                <p><?php echo nl2br( esc_html( $booking['notes'] ) ); ?></p>
            </div>
            <?php endif; ?>

        </div>
        <?php
    }
}
