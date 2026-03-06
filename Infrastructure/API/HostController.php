<?php
/**
 * Host Controller
 *
 * REST API endpoints for the Host Dashboard.
 * Separates all business and database logic from the theme templates.
 *
 * @package Himalayan\Homestay\Infrastructure\API
 */

namespace Himalayan\Homestay\Infrastructure\API;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class HostController {

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes() {
        // GET /wp-json/hhb/v1/host/overview
        register_rest_route( 'hhb/v1', '/host/overview', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_overview' ],
            'permission_callback' => [ __CLASS__, 'check_host_permission' ],
        ] );

        // GET /wp-json/hhb/v1/host/bookings
        register_rest_route( 'hhb/v1', '/host/bookings', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_bookings' ],
            'permission_callback' => [ __CLASS__, 'check_host_permission' ],
        ] );

        // GET /wp-json/hhb/v1/host/payouts
        register_rest_route( 'hhb/v1', '/host/payouts', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_payouts' ],
            'permission_callback' => [ __CLASS__, 'check_host_permission' ],
        ] );
        
        // GET /wp-json/hhb/v1/host/calendar
        register_rest_route( 'hhb/v1', '/host/calendar', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_calendar_events' ],
            'permission_callback' => [ __CLASS__, 'check_host_permission' ],
        ] );
    }

    /**
     * Permission Callback for host endpoints.
     */
    public static function check_host_permission( \WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error( 'unauthorized', 'You must be logged in.', [ 'status' => 401 ] );
        }
        
        // Enforce Phase 7.1 strict host capability boundary
        if ( ! current_user_can( 'manage_hhb_property' ) && ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error( 'forbidden', 'You do not have host permissions.', [ 'status' => 403 ] );
        }
        
        // Verify Nonce (WP REST API handles `_wpnonce` header natively, but we can enforce it if needed)
        // Actually, WP REST API already enforces nonce via `X-WP-Nonce` header if cookie auth is used.
        return true;
    }

    /**
     * Helper to get homestay IDs owned by current user.
     */
    private static function get_host_property_ids( $user_id ) {
        $props = get_posts([
            'post_type'      => 'hhb_homestay',
            'author'         => $user_id,
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft', 'pending'],
            'fields'         => 'ids',
        ]);
        return empty( $props ) ? [0] : $props; // Return [0] to safely use in IN() clauses
    }

    /**
     * GET /wp-json/hhb/v1/host/overview
     */
    public static function get_overview( \WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $homestay_ids = self::get_host_property_ids( $user_id );
        
        if ( $homestay_ids === [0] ) {
            return rest_ensure_response([
                'has_properties' => false,
                'message' => 'No active properties listed.'
            ]);
        }

        $homestay_in = implode( ',', array_map( 'intval', $homestay_ids ) );
        $bookings_table = $wpdb->prefix . 'himalayan_bookings';

        // 1. Total Earnings
        $total_earnings = $wpdb->get_var( "SELECT SUM(total_price) FROM {$bookings_table} WHERE homestay_id IN ({$homestay_in}) AND status = 'confirmed'" );

        // 2. Active Bookings
        $active_bookings = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$bookings_table} WHERE homestay_id IN ({$homestay_in}) AND status IN ('approved', 'confirmed') AND check_out >= %s",
            current_time( 'Y-m-d' )
        ) );

        // 3. Occupancy Rate
        $total_properties = count( $homestay_ids );
        $total_possible_nights = $total_properties * 30;
        $booked_nights_query = $wpdb->get_results( $wpdb->prepare(
            "SELECT check_in, check_out FROM {$bookings_table} WHERE homestay_id IN ({$homestay_in}) AND status = 'confirmed' AND check_in <= DATE_ADD(%s, INTERVAL 30 DAY) AND check_out >= %s",
            current_time( 'Y-m-d' ), current_time( 'Y-m-d' )
        ) ) ?: [];

        $booked_days = 0;
        foreach ( $booked_nights_query as $b ) {
            $in = new \DateTime( max( $b->check_in, current_time( 'Y-m-d' ) ) );
            $out = new \DateTime( min( $b->check_out, gmdate('Y-m-d', strtotime('+30 days')) ) );
            $diff = $in->diff( $out )->days;
            if ( $diff > 0 ) $booked_days += $diff;
        }
        $occupancy_rate = $total_possible_nights > 0 ? min( 100, round( ( $booked_days / $total_possible_nights ) * 100 ) ) : 0;

        // 4. Currency
        $currency = 'INR';
        $currency_symbols = [ 'USD' => '$', 'INR' => '₹', 'EUR' => '€', 'GBP' => '£', 'NPR' => 'रु' ];
        $sym = $currency_symbols[ strtoupper($currency) ] ?? $currency;

        // 5. Recent Bookings (Pre-processed JSON layout)
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no user input; $homestay_in is built from intval'd post IDs
        $recent_bookings_raw = $wpdb->get_results(
            "SELECT b.*, p.post_title as property_name
             FROM {$bookings_table} b
             LEFT JOIN {$wpdb->posts} p ON b.homestay_id = p.ID
             WHERE b.homestay_id IN ({$homestay_in})
             ORDER BY b.created_at DESC LIMIT 5"
        ) ?: [];

        $recent_bookings = [];
        foreach ( $recent_bookings_raw as $b ) {
            // Apply business rules for frontend badge rendering
            $badge_color = \Himalayan\Homestay\Domain\Booking\BookingStatus::get_color( $b->status );

            $in_date = new \DateTime($b->check_in);
            $out_date = new \DateTime($b->check_out);

            $recent_bookings[] = [
                'id' => $b->id,
                'customer_name' => $b->customer_name,
                'guests' => $b->guests,
                'property_name' => $b->property_name,
                'date_range' => $in_date->format('M j') . ' - ' . $out_date->format('M j, Y'),
                'status_label' => ucwords( str_replace('_', ' ', $b->status ) ),
                'status_color' => $badge_color,
                'formatted_price' => $sym . ' ' . number_format( (float)$b->total_price, 2 )
            ];
        }

        return rest_ensure_response([
            'has_properties' => true,
            'stats' => [
                'total_earnings' => $sym . ' ' . number_format( (float)$total_earnings, 2 ),
                'active_bookings' => number_format( (int)$active_bookings ),
                'occupancy_rate' => $occupancy_rate . '%'
            ],
            'recent_bookings' => $recent_bookings
        ]);
    }

    /**
     * GET /wp-json/hhb/v1/host/calendar
     */
    public static function get_calendar_events( \WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        
        // 1. Fetch properties for dropdown
        $host_homestays = get_posts([
            'post_type'      => 'hhb_homestay',
            'author'         => $user_id,
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft', 'pending'],
            'orderby'        => 'title',
            'order'          => 'ASC'
        ]);

        if ( empty( $host_homestays ) ) {
            return rest_ensure_response([ 'has_properties' => false ]);
        }

        $properties = [];
        foreach( $host_homestays as $p ) {
            $properties[] = [ 'id' => $p->ID, 'title' => $p->post_title ];
        }

        // 2. Determine selected property
        $req_prop_id = $request->get_param('property_id') ? intval($request->get_param('property_id')) : $properties[0]['id'];
        $selected_homestay_id = $properties[0]['id']; // Default fallback
        
        foreach ( $properties as $p ) {
            if ( $p['id'] === $req_prop_id ) {
                $selected_homestay_id = $req_prop_id;
                break;
            }
        }

        // 3. Determine month/year
        $month = $request->get_param('month') ? intval($request->get_param('month')) : (int) date('n');
        $year  = $request->get_param('year') ? intval($request->get_param('year')) : (int) date('Y');

        $first_day_of_month = mktime( 0, 0, 0, $month, 1, $year );
        $days_in_month      = (int) date( 't', $first_day_of_month );
        $day_of_week        = (int) date( 'w', $first_day_of_month ); // 0 (Sun) to 6 (Sat)
        $month_name         = date( 'F', $first_day_of_month );

        // 4. Calculate prev/next
        $prev_month = $month - 1; $prev_year = $year;
        if ( $prev_month < 1 ) { $prev_month = 12; $prev_year--; }
        $next_month = $month + 1; $next_year = $year;
        if ( $next_month > 12 ) { $next_month = 1; $next_year++; }

        // 5. Query Events
        $bookings_table = $wpdb->prefix . 'himalayan_bookings';
        $start_date_str = date( 'Y-m-d', $first_day_of_month );
        $end_date_str   = date( 'Y-m-t', $first_day_of_month );

        $events_raw = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, check_in, check_out, status, customer_name, 'booking' as type 
             FROM {$bookings_table} 
             WHERE homestay_id = %d 
             AND status IN ('confirmed', 'approved')
             AND check_out >= %s 
             AND check_in <= %s",
            $selected_homestay_id, $start_date_str, $end_date_str
        ) );

        $day_events = []; // Array mapping Day(int) -> array of events
        foreach ( $events_raw as $event ) {
            $in  = new \DateTime( $event->check_in );
            $out = new \DateTime( $event->check_out );
            $current = clone $in;
            while ( $current < $out ) {
                if ( (int)$current->format('n') === $month && (int)$current->format('Y') === $year ) {
                    $d = (int)$current->format('j');
                    if ( ! isset( $day_events[$d] ) ) $day_events[$d] = [];
                    $day_events[$d][] = [
                        'id' => $event->id,
                        'customer_name' => $event->customer_name,
                        'status' => $event->status,
                        'type' => $event->type
                    ];
                }
                $current->modify('+1 day');
            }
        }

        return rest_ensure_response([
            'has_properties' => true,
            'properties'     => $properties,
            'selected_property_id' => $selected_homestay_id,
            'nav' => [
                'current_month' => $month, 'current_year' => $year, 'month_name' => $month_name,
                'prev_month' => $prev_month, 'prev_year' => $prev_year,
                'next_month' => $next_month, 'next_year' => $next_year,
            ],
            'grid' => [
                'days_in_month' => $days_in_month,
                'first_day_of_week' => $day_of_week,
                'today' => ( (int)date('n') === $month && (int)date('Y') === $year ) ? (int)date('j') : 0
            ],
            'events' => $day_events
        ]);
    }

    /**
     * GET /wp-json/hhb/v1/host/payouts
     */
    public static function get_payouts( \WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $homestay_ids = self::get_host_property_ids( $user_id );
        
        if ( $homestay_ids === [0] ) {
            return rest_ensure_response([ 'has_properties' => false ]);
        }

        $prop_ids_placeholder = implode( ',', array_fill( 0, count( $homestay_ids ), '%d' ) );
        $bookings_table = $wpdb->prefix . 'himalayan_bookings';

        // 1. Lifetime Earnings
        $lifetime_earnings = floatval( $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(host_payout) FROM {$bookings_table} WHERE homestay_id IN ($prop_ids_placeholder) AND status IN ('confirmed', 'completed')",
            ...$homestay_ids
        ) ) );

        // 2. Upcoming Payouts
        $upcoming_payouts = floatval( $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(host_payout) FROM {$bookings_table} 
             WHERE homestay_id IN ($prop_ids_placeholder) AND status = 'confirmed' AND check_out >= CURDATE()",
            ...$homestay_ids
        ) ) );

        // 3. Payout History
        $history_raw = $wpdb->get_results( $wpdb->prepare(
            "SELECT b.id as booking_id, b.homestay_id, b.customer_name, b.check_in, b.check_out, b.total_price, b.admin_commission, b.host_payout, b.status 
             FROM {$bookings_table} b
             WHERE b.homestay_id IN ($prop_ids_placeholder) AND b.status IN ('confirmed', 'completed', 'cancelled')
             ORDER BY b.check_in DESC LIMIT 20",
            ...$homestay_ids
        ) );

        $currency = 'INR';
        $currency_symbols = [ 'USD' => '$', 'INR' => '₹', 'EUR' => '€', 'GBP' => '£', 'NPR' => 'रु' ];
        $sym = $currency_symbols[ strtoupper($currency) ] ?? $currency;

        $payout_history = [];
        foreach ( $history_raw as $row ) {
            $in_date = new \DateTime($row->check_in);
            $out_date = new \DateTime($row->check_out);
            
            $payout_history[] = [
                'booking_id' => $row->booking_id,
                'customer_name' => $row->customer_name,
                'dates' => $in_date->format('M j') . ' - ' . $out_date->format('M j, Y'),
                'total_price' => $sym . number_format($row->total_price, 2),
                'admin_commission' => '- ' . $sym . number_format($row->admin_commission, 2),
                'host_payout' => $sym . number_format($row->host_payout, 2),
                'status' => $row->status,
                'status_label' => $row->status === 'cancelled' ? 'Voided' : 'Processed',
                'status_color' => $row->status === 'cancelled' ? '#ef4444' : '#10b981'
            ];
        }

        return rest_ensure_response([
            'has_properties' => true,
            'lifetime_earnings' => $sym . number_format($lifetime_earnings, 2),
            'upcoming_payouts' => $sym . number_format($upcoming_payouts, 2),
            'payout_history' => $payout_history
        ]);
    }

    /**
     * GET /wp-json/hhb/v1/host/bookings
     */
    public static function get_bookings( \WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $homestay_ids = self::get_host_property_ids( $user_id );
        
        if ( $homestay_ids === [0] ) {
            return rest_ensure_response([ 'bookings' => [] ]);
        }

        $homestay_in = implode( ',', array_map( 'intval', $homestay_ids ) );
        $bookings_table = $wpdb->prefix . 'himalayan_bookings';
        
        // Add Pagination
        $page = $request->get_param('page') ? intval($request->get_param('page')) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        $total_bookings = $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE homestay_id IN ({$homestay_in})" );
        $total_pages = ceil( $total_bookings / $per_page );

        $bookings_raw = $wpdb->get_results( $wpdb->prepare(
            "SELECT b.*, p.post_title as property_name 
             FROM {$bookings_table} b
             LEFT JOIN {$wpdb->posts} p ON b.homestay_id = p.ID
             WHERE b.homestay_id IN ({$homestay_in})
             ORDER BY b.created_at DESC 
             LIMIT %d OFFSET %d",
             $per_page, $offset
        ) );

        $currency = 'INR';
        $currency_symbols = [ 'USD' => '$', 'INR' => '₹', 'EUR' => '€', 'GBP' => '£', 'NPR' => 'रु' ];
        $sym = $currency_symbols[ strtoupper($currency) ] ?? $currency;

        $bookings = [];
        foreach ( $bookings_raw as $b ) {
            $badge_color = \Himalayan\Homestay\Domain\Booking\BookingStatus::get_color( $b->status );

            $bookings[] = [
                'id' => $b->id,
                'customer_name' => $b->customer_name,
                'customer_email' => $b->customer_email,
                'customer_phone' => $b->customer_phone,
                'guests' => $b->guests,
                'property_name' => $b->property_name,
                'check_in' => $b->check_in,
                'check_out' => $b->check_out,
                'nights' => (new \DateTime($b->check_in))->diff(new \DateTime($b->check_out))->days,
                'status_label' => ucwords( str_replace('_', ' ', $b->status ) ),
                'status_color' => $badge_color,
                'formatted_price' => $sym . ' ' . number_format( (float)$b->total_price, 2 ),
                'can_cancel' => ($b->status === 'confirmed' || $b->status === 'approved')
            ];
        }

        return rest_ensure_response([ 
            'bookings'    => $bookings,
            'total_pages' => $total_pages,
            'current_page'=> $page
        ]);
    }
}
