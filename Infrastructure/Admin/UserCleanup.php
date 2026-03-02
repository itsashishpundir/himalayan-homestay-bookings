<?php
/**
 * Inactive User Cleanup
 *
 * Automatically deletes users with role 'subscriber' or 'customer' who have not
 * logged in for over 30 days. Uses a daily WP Cron event.
 *
 * @package Himalayan\Homestay\Infrastructure\Admin
 */

namespace Himalayan\Homestay\Infrastructure\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UserCleanup {

    const INACTIVITY_LIMIT_DAYS = 30;

    public static function init(): void {
        // Track logins
        add_action( 'wp_login', [ __CLASS__, 'update_last_login' ], 10, 2 );

        // Admin columns
        add_filter( 'manage_users_columns', [ __CLASS__, 'add_last_login_column' ] );
        add_filter( 'manage_users_custom_column', [ __CLASS__, 'render_last_login_column' ], 10, 3 );

        // Cron job
        add_action( 'hhb_daily_inactive_user_cleanup', [ __CLASS__, 'cleanup_inactive_users' ] );
        
        // Schedule if not already
        if ( ! wp_next_scheduled( 'hhb_daily_inactive_user_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'hhb_daily_inactive_user_cleanup' );
        }
    }

    /**
     * Update user meta on login.
     */
    public static function update_last_login( string $user_login, \WP_User $user ): void {
        update_user_meta( $user->ID, 'hhb_last_login', time() );
    }

    /**
     * Add column to WP Admin -> Users.
     */
    public static function add_last_login_column( array $columns ): array {
        $columns['last_login'] = __( 'Last Login', 'himalayan-homestay-bookings' );
        return $columns;
    }

    /**
     * Render the custom column.
     */
    public static function render_last_login_column( string $output, string $column_name, int $user_id ): string {
        if ( 'last_login' === $column_name ) {
            $last_login = get_user_meta( $user_id, 'hhb_last_login', true );
            if ( $last_login ) {
                return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_login );
            }
            return __( 'Never', 'himalayan-homestay-bookings' );
        }
        return $output;
    }

    /**
     * Cron callback: Delete inactive subscribers/customers.
     */
    public static function cleanup_inactive_users(): void {
        // We only target specific roles to avoid deleting admins or shop managers.
        $target_roles = [ 'subscriber', 'customer' ];
        $cutoff_time  = time() - ( self::INACTIVITY_LIMIT_DAYS * DAY_IN_SECONDS );

        $users = get_users( [
            'role__in' => $target_roles,
            'fields'   => 'all_with_meta',
        ] );

        require_once ABSPATH . 'wp-admin/includes/user.php';

        foreach ( $users as $user ) {
            // Check last login meta.
            $last_login = get_user_meta( $user->ID, 'hhb_last_login', true );

            $should_delete = false;

            if ( $last_login ) {
                // User has logged in before, but it was > 30 days ago.
                if ( $last_login < $cutoff_time ) {
                    $should_delete = true;
                }
            } else {
                // User has NEVER logged in. Check their registration date.
                $registered_time = strtotime( $user->user_registered );
                if ( $registered_time < $cutoff_time ) {
                    $should_delete = true;
                }
            }

            if ( $should_delete ) {
                // wp_delete_user safely reassigns or removes data. 
                // Since they are basic guests, they shouldn't own important posts.
                wp_delete_user( $user->ID );
            }
        }
    }
}
