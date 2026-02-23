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

        // Admin Interfaces.
        if ( is_admin() ) {
            \Himalayan\Homestay\Interface\Admin\HomestayMetaBoxes::init();
            \Himalayan\Homestay\Interface\Admin\BookingsPage::init();
            \Himalayan\Homestay\Interface\Admin\CalendarPage::init();
            \Himalayan\Homestay\Interface\Admin\TaxonomyMeta::init();
        }

        // REST API.
        \Himalayan\Homestay\Infrastructure\API\RESTController::init();

        // Email Notifications (hooks into booking lifecycle events).
        \Himalayan\Homestay\Infrastructure\Notifications\EmailNotifier::init();

        // Frontend Booking Widget.
        if ( ! is_admin() ) {
            \Himalayan\Homestay\Interface\Frontend\BookingWidget::init();
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
