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
