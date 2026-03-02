<?php
/**
 * WP Login Customizer
 *
 * Brands the default wp-login.php page to match the Laluri/Himalayan Homestays theme.
 *
 * @package Himalayan\Homestay\Interface\Frontend
 */

namespace Himalayan\Homestay\Interface\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LoginStyling {

    public static function init(): void {
        add_action( 'login_enqueue_scripts', [ __CLASS__, 'custom_login_css' ] );
        add_filter( 'login_headerurl', [ __CLASS__, 'custom_login_url' ] );
        add_filter( 'login_headertext', [ __CLASS__, 'custom_login_title' ] );
    }

    public static function custom_login_css(): void {
        // Use the site logo if available
        $custom_logo_id = get_theme_mod( 'custom_logo' );
        $logo_url = '';
        if ( $custom_logo_id ) {
            $logo_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
        }

        ?>
        <style type="text/css">
            body.login {
                background-color: #f9f9f9;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            #login h1 a, .login h1 a {
                <?php if ( $logo_url ) : ?>
                background-image: url(<?php echo esc_url( $logo_url ); ?>);
                background-size: contain;
                width: 100%;
                height: 80px;
                <?php else : ?>
                display: none;
                <?php endif; ?>
            }
            #login {
                width: 400px;
                padding: 5% 0 0;
            }
            .login form {
                background: #fff;
                border: none;
                box-shadow: 0 4px 24px rgba(0,0,0,0.06);
                border-radius: 12px;
                padding: 30px;
            }
            .login label {
                font-size: 13px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #555;
            }
            .login input[type="text"],
            .login input[type="password"],
            .login input[type="email"] {
                border-radius: 8px;
                border: 1px solid #ddd;
                padding: 10px 15px;
                box-shadow: none;
                font-size: 15px;
            }
            .login input[type="text"]:focus,
            .login input[type="password"]:focus,
            .login input[type="email"]:focus {
                border-color: #f45c25;
                box-shadow: 0 0 0 3px rgba(244,92,37,0.1);
            }
            .login .button-primary {
                background: #111 !important;
                border-color: #111 !important;
                color: #fff !important;
                border-radius: 8px;
                text-shadow: none;
                box-shadow: none;
                padding: 0 20px;
                font-size: 15px;
                font-weight: 600;
                height: 44px;
                width: 100%;
                margin-top: 15px;
                transition: background 0.2s;
            }
            .login .button-primary:hover {
                background: #333 !important;
            }
            .login #backtoblog, .login #nav {
                text-align: center;
                margin-top: 20px;
            }
            .login #backtoblog a, .login #nav a {
                color: #666 !important;
                text-decoration: none;
            }
            .login #backtoblog a:hover, .login #nav a:hover {
                color: #f45c25 !important;
            }
        </style>
        <?php
    }

    public static function custom_login_url(): string {
        return home_url( '/' );
    }

    public static function custom_login_title(): string {
        return get_bloginfo( 'name' );
    }
}
