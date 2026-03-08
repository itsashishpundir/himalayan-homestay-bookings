<?php
/**
 * Wishlist Handler
 *
 * Handles AJAX requests to save or remove homestays from a user's wishlist.
 *
 * @package Himalayan\Homestay\Interface\Frontend
 */

namespace Himalayan\Homestay\Interface\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WishlistHandler {

    public static function init(): void {
        add_action( 'wp_ajax_hhb_toggle_wishlist',        [ __CLASS__, 'handle_toggle' ] );
        add_action( 'wp_ajax_nopriv_hhb_toggle_wishlist', [ __CLASS__, 'handle_nopriv_toggle' ] );
        add_action( 'wp_footer',                          [ __CLASS__, 'output_login_modal' ] );
    }

    public static function handle_toggle(): void {
        check_ajax_referer( 'hhb_wishlist_nonce', 'security' );

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_send_json_error( 'Invalid post ID.' );
        }

        $user_id = get_current_user_id();
        $wishlist = get_user_meta( $user_id, 'hhb_wishlist', true );
        
        if ( ! is_array( $wishlist ) ) {
            $wishlist = [];
        }

        $is_favorited = false;
        
        if ( in_array( $post_id, $wishlist ) ) {
            // Remove it
            $wishlist = array_diff( $wishlist, [ $post_id ] );
        } else {
            // Add it
            $wishlist[] = $post_id;
            $is_favorited = true;
        }

        $wishlist = array_values( array_unique( $wishlist ) );
        update_user_meta( $user_id, 'hhb_wishlist', $wishlist );

        wp_send_json_success( [
            'is_favorited' => $is_favorited,
            'message'      => $is_favorited ? 'Added to wishlist!' : 'Removed from wishlist.'
        ] );
    }

    public static function handle_nopriv_toggle(): void {
        // For logged-out users, we could use cookies/local storage via JS,
        // but for now, just prompt them to log in.
        wp_send_json_error( 'Please log in to save properties to your wishlist.', 401 );
    }

    public static function output_login_modal(): void {
        if ( is_user_logged_in() ) {
            // Already logged in — just expose the flag, no modal needed.
            echo '<script>window.hhbIsLoggedIn=true;</script>';
            return;
        }

        $login_url = esc_url( wp_login_url( get_permalink() ) );
        $account_url = esc_url( home_url( '/my-account/' ) );
        ?>
        <script>window.hhbIsLoggedIn=false;</script>

        <!-- HHB Wishlist Login Modal -->
        <div id="hhb-wishlist-modal" style="display:none;position:fixed;inset:0;z-index:99999;align-items:center;justify-content:center;">
            <!-- Backdrop -->
            <div id="hhb-wl-backdrop" style="position:absolute;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);"></div>
            <!-- Card -->
            <div style="position:relative;background:#fff;border-radius:24px;padding:40px 36px;max-width:380px;width:calc(100% - 32px);text-align:center;box-shadow:0 24px 64px rgba(0,0,0,.18);animation:hhbModalIn .25s ease;">
                <button id="hhb-wl-close" aria-label="Close" style="position:absolute;top:16px;right:16px;background:none;border:none;cursor:pointer;color:#94a3b8;font-size:22px;line-height:1;padding:4px;">&#x2715;</button>

                <div style="width:56px;height:56px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="#ef4444"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                </div>

                <h3 style="margin:0 0 8px;font-size:20px;font-weight:800;color:#0f172a;font-family:'Inter',sans-serif;">Save to Wishlist</h3>
                <p style="margin:0 0 28px;font-size:14px;color:#64748b;font-family:'Inter',sans-serif;line-height:1.6;">You need to be logged in to save properties to your wishlist.</p>

                <a href="<?php echo $account_url; ?>" style="display:block;background:#e85e30;color:#fff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 24px;border-radius:12px;font-family:'Inter',sans-serif;margin-bottom:10px;transition:background .2s;">Log in to continue</a>
                <a href="<?php echo $account_url; ?>" style="display:block;color:#64748b;text-decoration:none;font-size:13px;font-family:'Inter',sans-serif;padding:8px;">Don't have an account? <span style="color:#e85e30;font-weight:600;">Register free</span></a>
            </div>
        </div>

        <style>
        @keyframes hhbModalIn {
            from { opacity:0; transform:scale(.92) translateY(12px); }
            to   { opacity:1; transform:scale(1)  translateY(0); }
        }
        </style>

        <script>
        (function() {
            var modal    = document.getElementById('hhb-wishlist-modal');
            var backdrop = document.getElementById('hhb-wl-backdrop');
            var closeBtn = document.getElementById('hhb-wl-close');

            function openModal()  { modal.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
            function closeModal() { modal.style.display = 'none';  document.body.style.overflow = ''; }

            // Capture phase — fires before any other click handler on .hhb-wishlist-toggle
            document.addEventListener('click', function(e) {
                if ( window.hhbIsLoggedIn ) return;
                var btn = e.target.closest('.hhb-wishlist-toggle');
                if ( ! btn ) return;
                e.preventDefault();
                e.stopImmediatePropagation();
                openModal();
            }, true);

            backdrop.addEventListener('click', closeModal);
            closeBtn.addEventListener('click', closeModal);
            document.addEventListener('keydown', function(e) {
                if ( e.key === 'Escape' ) closeModal();
            });
        })();
        </script>
        <?php
    }

    /**
     * Check if a specific homestay is in the current user's wishlist
     */
    public static function is_in_wishlist( int $post_id ): bool {
        if ( ! is_user_logged_in() ) return false;
        
        $wishlist = get_user_meta( get_current_user_id(), 'hhb_wishlist', true );
        if ( ! is_array( $wishlist ) ) return false;

        return in_array( $post_id, $wishlist );
    }
}
