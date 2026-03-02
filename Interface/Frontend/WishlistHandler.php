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
        add_action( 'wp_ajax_hhb_toggle_wishlist', [ __CLASS__, 'handle_toggle' ] );
        add_action( 'wp_ajax_nopriv_hhb_toggle_wishlist', [ __CLASS__, 'handle_nopriv_toggle' ] );
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
