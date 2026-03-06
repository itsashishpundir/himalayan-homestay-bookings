<?php
/**
 * Plugin Name: Himalayan Homestay Bookings
 * Plugin URI:  https://himalyanmart.test
 * Description: A professional, enterprise-grade homestay booking system. Features custom DB tables, event-driven architecture, advanced pricing engine, availability calendar, email notifications, extra services, and a dynamic frontend booking widget.
 * Version:     2.0.0
 * Author:      Himalayan Team
 * Text Domain: himalayan-homestay-bookings
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'HHB_VERSION', '2.0.0' );
define( 'HHB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HHB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * PSR-4 Style Autoloader
 */
spl_autoload_register( function ( $class ) {
    $prefix   = 'Himalayan\\Homestay\\';
    $base_dir = HHB_PLUGIN_DIR;
    $len      = strlen( $prefix );

    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    $file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
} );

/**
 * Main Plugin Class
 */
final class Himalayan_Homestay_Bookings {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_core();
        $this->init_hooks();
    }

    private function init_core() {
        // Register Post Types & Taxonomies.
        \Himalayan\Homestay\Core\PostTypes::init();
        \Himalayan\Homestay\Core\Taxonomies::init();

        // ICAL Sync
        require_once plugin_dir_path( __FILE__ ) . 'Infrastructure/ICAL/iCalManager.php';
        \Himalayan\Homestay\Infrastructure\ICAL\iCalManager::init();

        // Admin Interfaces.
        if ( is_admin() ) {
            \Himalayan\Homestay\Interface\Admin\HomestayMetaBoxes::init();
            // RoomMetaBoxes removed — rooms are managed inside the Homestay edit screen.
            \Himalayan\Homestay\Interface\Admin\BookingsPage::init();
            \Himalayan\Homestay\Interface\Admin\CalendarPage::init();
            \Himalayan\Homestay\Interface\Admin\TaxonomyMeta::init();

            require_once plugin_dir_path( __FILE__ ) . 'Interface/Admin/CouponsPage.php';
            \Himalayan\Homestay\Interface\Admin\CouponsPage::init();

            require_once plugin_dir_path( __FILE__ ) . 'Interface/Admin/HostApplicationsPage.php';
            \Himalayan\Homestay\Interface\Admin\HostApplicationsPage::init();

            require_once plugin_dir_path( __FILE__ ) . 'Interface/Admin/GuestUsersPage.php';
            \Himalayan\Homestay\Interface\Admin\GuestUsersPage::init();

            // Admin Dashboard Analytics (Phase 10)
            require_once plugin_dir_path( __FILE__ ) . 'Interface/Admin/AdminDashboard.php';
            \Himalayan\Homestay\Interface\Admin\AdminDashboard::init();
        }

        // REST API.
        \Himalayan\Homestay\Infrastructure\API\RESTController::init();

        // ── One-time migration: fix old manual blocks stored as 'confirmed' ──
        add_action( 'init', function() {
            if ( get_transient( 'hhb_blocks_migrated_v1' ) ) return;
            global $wpdb;
            $wpdb->query( "
                UPDATE {$wpdb->prefix}himalayan_bookings
                SET status = 'blocked'
                WHERE status = 'confirmed'
                  AND customer_email = 'block@internal'
            " );
            set_transient( 'hhb_blocks_migrated_v1', 1, YEAR_IN_SECONDS );
        }, 5 );

        // ── One-time migration: add payment_expires_at column ──
        add_action( 'init', function() {
            if ( get_transient( 'hhb_schema_migrated_v260' ) ) return;
            global $wpdb;
            $table = $wpdb->prefix . 'himalayan_bookings';
            $col   = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'payment_expires_at'" );
            if ( empty( $col ) ) {
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN payment_expires_at datetime DEFAULT NULL AFTER payment_token" );
            }
            set_transient( 'hhb_schema_migrated_v260', 1, YEAR_IN_SECONDS );
        }, 5 );

        // ── Payment Expiry Cron: runs every 5 minutes ──
        // Finds approved bookings past their payment deadline → expires them.
        add_action( 'hhb_check_payment_expiry', function() {
            global $wpdb;
            $table = $wpdb->prefix . 'himalayan_bookings';
            $now   = current_time( 'mysql', 1 );

            $expired = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE status = 'approved'
                   AND payment_expires_at IS NOT NULL
                   AND payment_expires_at < %s",
                $now
            ) );

            foreach ( $expired as $booking ) {
                // 1. Update status to payment_expired.
                $wpdb->update( $table,
                    [ 'status' => 'payment_expired' ],
                    [ 'id'     => $booking->id ],
                    [ '%s' ], [ '%d' ]
                );

                // 2. Send expiry email to guest.
                if ( class_exists( '\Himalayan\Homestay\Infrastructure\Notifications\EmailNotifier' ) ) {
                    \Himalayan\Homestay\Infrastructure\Notifications\EmailNotifier::on_payment_expired( $booking->id );
                }

                // 3. Log for cron tracking.
                update_option( 'hhb_cron_last_ran_hhb_check_payment_expiry', time() );
            }
        } );

        // Email Notifications & PDFs
        \Himalayan\Homestay\Infrastructure\Notifications\EmailNotifier::init();
        \Himalayan\Homestay\Infrastructure\Notifications\EmailAutomator::init();
        require_once plugin_dir_path( __FILE__ ) . 'Infrastructure/PDF/InvoiceGenerator.php';
        \Himalayan\Homestay\Infrastructure\PDF\InvoiceGenerator::init();

        // Auto-create payout records on booking confirmation.
        \Himalayan\Homestay\Domain\Booking\BookingManager::init_payout_hooks();

        // Database Archiver (Phase 6)
        require_once plugin_dir_path( __FILE__ ) . 'Infrastructure/Database/ArchiverEngine.php';
        \Himalayan\Homestay\Infrastructure\Database\ArchiverEngine::init();

        // Automated Guest Reviews (Phase 12)
        require_once plugin_dir_path( __FILE__ ) . 'Infrastructure/Reviews/ReviewManager.php';
        \Himalayan\Homestay\Infrastructure\Reviews\ReviewManager::init();

        // Host Dashboard REST API (Phase 7.1)
        require_once plugin_dir_path( __FILE__ ) . 'Infrastructure/API/HostController.php';
        \Himalayan\Homestay\Infrastructure\API\HostController::init();

        // Auto-Delete Inactive Users
        require_once plugin_dir_path( __FILE__ ) . 'Infrastructure/Admin/UserCleanup.php';
        \Himalayan\Homestay\Infrastructure\Admin\UserCleanup::init();

        // Custom WP Login Styling
        require_once plugin_dir_path( __FILE__ ) . 'Interface/Frontend/LoginStyling.php';
        \Himalayan\Homestay\Interface\Frontend\LoginStyling::init();

        // Admin Settings Page (Phase 10)
        require_once plugin_dir_path( __FILE__ ) . 'Interface/Admin/SettingsPage.php';
        \Himalayan\Homestay\Interface\Admin\SettingsPage::init();

        // Admin Reviews Page
        require_once plugin_dir_path( __FILE__ ) . 'Interface/Admin/ReviewsPage.php';
        \Himalayan\Homestay\Interface\Admin\ReviewsPage::init();

        // Admin System Tools Page
        require_once plugin_dir_path( __FILE__ ) . 'Interface/Admin/SystemToolsPage.php';
        \Himalayan\Homestay\Interface\Admin\SystemToolsPage::init();

        // Admin Payouts & Financial Reports
        require_once plugin_dir_path( __FILE__ ) . 'Interface/Admin/PayoutsPage.php';
        \Himalayan\Homestay\Interface\Admin\PayoutsPage::init();

        require_once plugin_dir_path( __FILE__ ) . 'Interface/Admin/FinancialReportsPage.php';
        \Himalayan\Homestay\Interface\Admin\FinancialReportsPage::init();

        // Host Application AJAX handler must be registered globally (AJAX runs via admin-ajax.php,
        // so is_admin() returns true there — init() must NOT be inside the ! is_admin() block).
        \Himalayan\Homestay\Frontend\HostApplication::init();
        \Himalayan\Homestay\Frontend\PropertyManager::init();

        // Grant missing capabilities to hhb_host users on every request.
        // This is needed because add_role() only sets caps for newly-created roles;
        add_action( 'init', function() {
            $user = wp_get_current_user();
            if ( $user->exists() && in_array( 'hhb_host', (array) $user->roles, true ) ) {
                if ( ! $user->has_cap( 'upload_files' ) ) {
                    $user->add_cap( 'upload_files' );
                }
                if ( ! $user->has_cap( 'manage_hhb_property' ) ) {
                    $user->add_cap( 'manage_hhb_property' );
                }
            }
        }, 1 );

        // Frontend Booking Widget & Pages.
        if ( ! is_admin() ) {
            // Template Loader — serves plugin default templates for CPT / taxonomy / custom dashboard pages.
            // The theme always wins if it provides the same template filename.
            new \Himalayan\Homestay\Interface\Frontend\TemplateLoader();

            \Himalayan\Homestay\Interface\Frontend\BookingWidget::init();
            \Himalayan\Homestay\Interface\Frontend\ConfirmationPage::init();
            \Himalayan\Homestay\Interface\Frontend\MyAccount::init();
            require_once plugin_dir_path( __FILE__ ) . 'Interface/Frontend/ReviewPage.php';
            \Himalayan\Homestay\Interface\Frontend\ReviewPage::init();
            require_once plugin_dir_path( __FILE__ ) . 'Interface/Frontend/ReviewDisplay.php';
            \Himalayan\Homestay\Interface\Frontend\ReviewDisplay::init();
            // SEO: Schema markup, JSON-LD, meta tags, Open Graph (Phase 2).
            require_once plugin_dir_path( __FILE__ ) . 'Interface/Frontend/HomestaySchemaManager.php';
            \Himalayan\Homestay\Interface\Frontend\HomestaySchemaManager::init();

            // GDPR Compliance: Cookie Banner (Phase 5).
            add_action('wp_footer', function() {
                require_once plugin_dir_path( __FILE__ ) . 'Interface/Frontend/CookieBanner.php';
            });

            // GDPR Compliance: Data Erasure Request Form (Phase 5).
            require_once plugin_dir_path( __FILE__ ) . 'Interface/Frontend/DataDeletionRequest.php';
            \Himalayan\Homestay\Interface\Frontend\DataDeletionRequest::init();

            // GDPR Compliance: Personal Data Eraser Hooks (Phase 5).
            if ( is_admin() ) {
                require_once plugin_dir_path( __FILE__ ) . 'Infrastructure/Admin/PersonalDataEraser.php';
                \Himalayan\Homestay\Infrastructure\Admin\PersonalDataEraser::init();
            }

            // Wishlist Handler (Phase 15)
            require_once plugin_dir_path( __FILE__ ) . 'Interface/Frontend/WishlistHandler.php';
            \Himalayan\Homestay\Interface\Frontend\WishlistHandler::init();
        }
    }

    private function init_hooks() {
        register_activation_hook( __FILE__, [ '\Himalayan\Homestay\Infrastructure\Database\Installer', 'install' ] );

        // Run DB upgrade on version change.
        add_action( 'plugins_loaded', [ $this, 'maybe_upgrade_db' ] );

        // Custom Cron Schedules.
        add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );

        // Cron Action.
        add_action( 'himalayan_cleanup_expired_holds', [ $this, 'cleanup_holds' ] );
        // The iCal sync action is registered within iCalManager::init()

        // ── Archive Filter Query ─────────────────────────────────────────────
        // Filter the main WP_Query on the hhb_homestay archive by ?location= and ?type= GET params.
        // This was previously in the theme's functions.php — now owned by the plugin.
        add_action( 'pre_get_posts', function( $query ) {
            if ( is_admin() || ! $query->is_main_query() ) {
                return;
            }
            if ( ! $query->is_post_type_archive( 'hhb_homestay' ) && ! $query->is_tax( 'hhb_location' ) && ! $query->is_tax( 'hhb_property_type' ) ) {
                return;
            }

            $tax_query = [];

            $location = isset( $_GET['location'] ) ? sanitize_text_field( $_GET['location'] ) : '';
            if ( $location ) {
                $tax_query[] = [
                    'taxonomy' => 'hhb_location',
                    'field'    => 'slug',
                    'terms'    => $location,
                ];
            }

            $type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';
            if ( $type ) {
                $tax_query[] = [
                    'taxonomy' => 'hhb_property_type',
                    'field'    => 'slug',
                    'terms'    => $type,
                ];
            }

            if ( ! empty( $tax_query ) ) {
                $query->set( 'tax_query', $tax_query );
            }
        } );

        // ── SMTP Configuration ───────────────────────────────────────────────
        // Configures PHPMailer to use SMTP settings stored via the plugin's Settings page.
        // Was previously in the theme's functions.php — now owned by the plugin.
        add_action( 'phpmailer_init', function( $phpmailer ) {
            $smtp_email = get_option( 'hhb_smtp_email' );
            $smtp_pass  = get_option( 'hhb_smtp_pass' );

            if ( $smtp_email && $smtp_pass ) {
                $phpmailer->isSMTP();
                $phpmailer->Host       = 'smtp.gmail.com';
                $phpmailer->SMTPAuth   = true;
                $phpmailer->Port       = 465;
                $phpmailer->Username   = $smtp_email;
                $phpmailer->Password   = $smtp_pass;
                $phpmailer->SMTPSecure = 'ssl';
                $phpmailer->From       = $smtp_email;
                $from_name             = get_option( 'hhb_smtp_from_name' );
                $phpmailer->FromName   = ! empty( $from_name ) ? $from_name : get_bloginfo( 'name' );
            }
        } );
    }


    /**
     * Auto-upgrade database schema when the plugin version changes.
     */
    public function maybe_upgrade_db() {
        $installed_version = get_option( 'hhb_db_version', '0' );
        if ( version_compare( $installed_version, \Himalayan\Homestay\Infrastructure\Database\Installer::DB_VERSION, '<' ) ) {
            \Himalayan\Homestay\Infrastructure\Database\Installer::install();
        }
    }

    public function add_cron_schedules( $schedules ) {
        $schedules['five_minutes'] = [
            'interval' => 300,
            'display'  => __( 'Every 5 Minutes', 'himalayan-homestay-bookings' ),
        ];
        $schedules['fifteen_minutes'] = [
            'interval' => 900,
            'display'  => __( 'Every 15 Minutes', 'himalayan-homestay-bookings' ),
        ];
        return $schedules;
    }

    public function cleanup_holds() {
        $availability = new \Himalayan\Homestay\Domain\Availability\AvailabilityEngine();
        $availability->release_expired_holds();
    }
}

function HHB() {
    return Himalayan_Homestay_Bookings::instance();
}

// Ensure the installer is loaded directly for the activation hook.
require_once HHB_PLUGIN_DIR . 'Infrastructure/Database/Installer.php';

$GLOBALS['himalayan_homestay_bookings'] = HHB();

/**
 * Get the price range (min–max base price) for a homestay based on its rooms.
 *
 * Reads cached meta set on homestay save. Falls back to a live query if meta is absent.
 * Theme usage: $range = hhb_get_price_range( get_the_ID() );
 *
 * @param int $homestay_id
 * @return array|null ['min'=>float,'max'=>float,'formatted'=>string] or null if no rooms.
 */
function hhb_get_price_range( int $homestay_id ): ?array {
    $min = get_post_meta( $homestay_id, 'hhb_price_min', true );
    $max = get_post_meta( $homestay_id, 'hhb_price_max', true );

    // Live fallback: calculate from child rooms if meta not cached yet.
    if ( $min === '' || $max === '' ) {
        $rooms = get_posts( array(
            'post_type'      => 'hhb_room',
            'post_parent'    => $homestay_id,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ) );

        if ( empty( $rooms ) ) {
            return null;
        }

        $prices = array();
        foreach ( $rooms as $room_id ) {
            $price = (float) get_post_meta( $room_id, 'room_base_price', true );
            if ( $price > 0 ) {
                $prices[] = $price;
            }
        }

        if ( empty( $prices ) ) {
            return null;
        }

        $min = min( $prices );
        $max = max( $prices );

        // Cache for next request.
        update_post_meta( $homestay_id, 'hhb_price_min', $min );
        update_post_meta( $homestay_id, 'hhb_price_max', $max );
    }

    $min = (float) $min;
    $max = (float) $max;

    $formatted = $min === $max
        ? '₹' . number_format( $min, 0 )
        : '₹' . number_format( $min, 0 ) . ' – ₹' . number_format( $max, 0 );

    return array(
        'min'       => $min,
        'max'       => $max,
        'formatted' => $formatted,
    );
}
