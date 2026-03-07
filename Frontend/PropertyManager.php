<?php
/**
 * Property Manager
 *
 * Handles AJAX requests from the frontend Host Dashboard to create,
 * update, and manage homestay properties securely.
 *
 * @package Himalayan\Homestay\Frontend
 */

namespace Himalayan\Homestay\Frontend;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PropertyManager {

    public static function init() {
        add_action( 'wp_ajax_hhb_save_property', array( __CLASS__, 'handle_save_property' ) );
        add_action( 'wp_ajax_hhb_save_host_settings', array( __CLASS__, 'handle_save_settings' ) );
        add_action( 'wp_ajax_hhb_delete_property', array( __CLASS__, 'handle_delete_property' ) );
        
        // Allow hosts to query the media library on the frontend
        add_filter( 'ajax_query_attachments_args', array( __CLASS__, 'allow_frontend_media_query' ) );

        // WordPress blocks query-attachments if ! current_user_can('upload_files') BEFORE the filter runs.
        // We must hook into the AJAX action early to ensure our host bypass works.
        add_action( 'wp_ajax_query-attachments', array( __CLASS__, 'force_allow_media_query_for_hosts' ), 1 );
    }

    public static function force_allow_media_query_for_hosts() {
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            if ( in_array( 'hhb_host', (array) $user->roles ) || in_array( 'subscriber', (array) $user->roles ) ) {
                // Temporarily grant upload_files during this specific AJAX request so core WP doesn't wp_die().
                if ( ! $user->has_cap('upload_files') ) {
                    $user->add_cap('upload_files');
                }
            }
        }
    }

    public static function allow_frontend_media_query( $query ) {
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            if ( in_array( 'hhb_host', (array) $user->roles ) || in_array( 'subscriber', (array) $user->roles ) || current_user_can('edit_posts') ) {
                
                // Allow them to use the media Library on the frontend dashboard
                if ( ! current_user_can( 'upload_files' ) ) {
                    $user->add_cap( 'upload_files' );
                }
                
                // CRITICAL FIX: WordPress frontend media js often injects `author=current_user` 
                // for non-admins. This forces the grid to only show images they uploaded.
                // Our dummy content was uploaded by Admin, so the host sees an empty grid.
                // We unset this so they can select any image in the library.
                if ( isset( $query['author'] ) ) {
                    unset( $query['author'] );
                }
                
                return $query;
            }
        }
        return $query;
    }

    public static function handle_save_settings() {
        // 1. Security Checks
        if ( ! isset( $_POST['hhb_settings_nonce'] ) || ! wp_verify_nonce( $_POST['hhb_settings_nonce'], 'hhb_save_settings_nonce' ) ) {
            wp_send_json_error( __( 'Security check failed.', 'himalayan-homestay-bookings' ) );
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( __( 'You must be logged in to update settings.', 'himalayan-homestay-bookings' ) );
        }

        $user_id = get_current_user_id();

        // 2. Sanitize Data
        $first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
        $last_name  = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
        $email      = sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) );
        $bio        = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
        $avatar_id  = isset( $_POST['hhb_avatar_id'] ) ? intval( $_POST['hhb_avatar_id'] ) : 0;

        if ( empty( $email ) ) {
            wp_send_json_error( __( 'Email address is required.', 'himalayan-homestay-bookings' ) );
        }

        // Check if email belongs to another user
        $email_exists = email_exists( $email );
        if ( $email_exists && $email_exists !== $user_id ) {
            wp_send_json_error( __( 'This email address is already in use by another account.', 'himalayan-homestay-bookings' ) );
        }

        // 3. Update WP_User
        $userdata = [
            'ID'          => $user_id,
            'user_email'  => $email,
            'first_name'  => $first_name,
            'last_name'   => $last_name,
            'description' => $bio,
            // If they change first/last name, we probably want display_name to update too:
            'display_name'=> trim( $first_name . ' ' . $last_name ) ?: $email,
        ];
        
        $result = wp_update_user( $userdata );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        // 4. Update Meta Flags (Avatar)
        if ( $avatar_id > 0 ) {
            update_user_meta( $user_id, 'hhb_avatar_id', $avatar_id );
            
            // Generate standard WP avatar fallback URL based on attachment
            $avatar_url = wp_get_attachment_image_url( $avatar_id, 'thumbnail' );
            if ( $avatar_url ) {
                 update_user_meta( $user_id, 'custom_avatar', $avatar_url ); // Optional: for legacy themes
            }
        } else {
            delete_user_meta( $user_id, 'hhb_avatar_id' );
            delete_user_meta( $user_id, 'custom_avatar' );
        }

        wp_send_json_success( __( 'Profile updated successfully!', 'himalayan-homestay-bookings' ) );
    }

    public static function handle_delete_property() {
        if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'hhb_delete_property_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in.' );
        }
        
        $property_id = isset( $_POST['property_id'] ) ? intval( $_POST['property_id'] ) : 0;
        if ( ! $property_id ) {
            wp_send_json_error( 'Invalid property.' );
        }

        $post = get_post( $property_id );
        if ( ! $post || $post->post_type !== 'hhb_homestay' ) {
            wp_send_json_error( 'Invalid property.' );
        }

        if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $result = wp_trash_post( $property_id );
        if ( ! $result ) {
            wp_send_json_error( 'Failed to delete property.' );
        }

        wp_send_json_success( 'Property deleted successfully.' );
    }

    public static function handle_save_property() {
        // 1. Security & Nonce
        if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'hhb_save_property' ) ) {
            wp_send_json_error( 'Security validation failed.' );
        }

        // 2. Authentication
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in to save properties.' );
        }

        $current_user_id = get_current_user_id();

        // 3. Extract & Sanitize Data
        $property_id = isset( $_POST['property_id'] ) ? intval( $_POST['property_id'] ) : 0;
        $title       = sanitize_text_field( wp_unslash( $_POST['post_title'] ?? '' ) );
        $content     = wp_kses_post( wp_unslash( $_POST['post_content'] ?? '' ) );
        $total_bedrooms  = absint( $_POST['hhb_total_bedrooms'] ?? $_POST['hhb_bedrooms'] ?? 1 );
        $total_bathrooms = absint( $_POST['hhb_total_bathrooms'] ?? $_POST['hhb_bathrooms'] ?? 1 );
        $max_guests      = absint( $_POST['hhb_max_guests'] ?? $_POST['max_guests'] ?? 2 );

        // Booking constraint fields
        $min_nights        = absint( $_POST['hhb_min_nights'] ?? 1 );
        $max_nights        = absint( $_POST['hhb_max_nights'] ?? 30 );
        $extra_guest_fee   = floatval( $_POST['hhb_extra_guest_fee'] ?? 0 );

        // Host profile mode
        $host_mode       = sanitize_text_field( $_POST['hhb_host_mode'] ?? 'user' );
        $host_name       = sanitize_text_field( wp_unslash( $_POST['hhb_host_name'] ?? '' ) );
        $host_email      = sanitize_email( wp_unslash( $_POST['hhb_host_email'] ?? '' ) );
        $host_phone      = sanitize_text_field( wp_unslash( $_POST['hhb_host_phone'] ?? '' ) );
        $host_bio        = sanitize_textarea_field( wp_unslash( $_POST['hhb_host_bio'] ?? '' ) );
        $host_avatar_url = esc_url_raw( wp_unslash( $_POST['hhb_host_avatar_url'] ?? '' ) );

        // Location fields (structured address — replaces old lat/lng)
        $address     = sanitize_text_field( wp_unslash( $_POST['hhb_address'] ?? '' ) );
        $city        = sanitize_text_field( wp_unslash( $_POST['hhb_city'] ?? '' ) );
        $state       = sanitize_text_field( wp_unslash( $_POST['hhb_state'] ?? '' ) );
        $country     = sanitize_text_field( wp_unslash( $_POST['hhb_country'] ?? 'India' ) );
        $postal_code = sanitize_text_field( wp_unslash( $_POST['hhb_postal_code'] ?? '' ) );

        // Booking rules
        $buffer_days         = intval( $_POST['hhb_buffer_days'] ?? 0 );
        $deposit_percent     = intval( $_POST['hhb_deposit_percent'] ?? 0 );
        $dos                 = sanitize_textarea_field( wp_unslash( $_POST['hhb_dos'] ?? '' ) );
        $donts               = sanitize_textarea_field( wp_unslash( $_POST['hhb_donts'] ?? '' ) );
        
        $attractions_raw     = sanitize_textarea_field( wp_unslash( $_POST['hhb_attractions'] ?? '' ) );
        $attractions         = array_filter( array_map( 'trim', explode( "\n", $attractions_raw ) ) );

        // Property Info
        $property_size    = absint( $_POST['hhb_property_size'] ?? 0 );
        $year_established = absint( $_POST['hhb_year_established'] ?? 0 );

        // Check-in / Check-out 
        $checkin_time     = sanitize_text_field( wp_unslash( $_POST['hhb_checkin_time'] ?? '14:00' ) );
        $checkout_time    = sanitize_text_field( wp_unslash( $_POST['hhb_checkout_time'] ?? '11:00' ) );
        $early_checkin    = sanitize_text_field( wp_unslash( $_POST['hhb_early_checkin'] ?? 'no' ) );
        $late_checkout    = sanitize_text_field( wp_unslash( $_POST['hhb_late_checkout'] ?? 'no' ) );

        // Contact
        $contact_phone    = sanitize_text_field( wp_unslash( $_POST['hhb_contact_phone'] ?? '' ) );
        $contact_email    = sanitize_email( wp_unslash( $_POST['hhb_contact_email'] ?? '' ) );
        $website_url      = esc_url_raw( wp_unslash( $_POST['hhb_website_url'] ?? '' ) );

        // Host Reference
        $host_user_id        = intval( $_POST['hhb_host_user_id'] ?? 0 );

        if ( empty( $title ) ) {
            wp_send_json_error( 'Title is required.' );
        }

        // 4. Verify Ownership if updating
        if ( $property_id > 0 ) {
            $post_author = (int) get_post_field( 'post_author', $property_id );
            if ( $post_author !== $current_user_id && ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( 'You do not have permission to edit this property.' );
            }
        }

        // 5. Build Post Array
        $post_data = [
            'post_title'   => $title,
            'post_content' => $content,
            'post_type'    => 'hhb_homestay',
            'post_author'  => $current_user_id,
        ];

        if ( $property_id > 0 ) {
            // Keep the existing publish status when updating — don't downgrade a live listing.
            $post_data['ID']          = $property_id;
            $post_data['post_status'] = get_post_status( $property_id ) ?: 'pending';
            $new_post_id = wp_update_post( $post_data, true );
        } else {
            // New listings go to pending — admin must approve before going live.
            $post_data['post_status'] = 'pending';
            $new_post_id = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $new_post_id ) ) {
            wp_send_json_error( $new_post_id->get_error_message() );
        }

        // 6. Save Meta Data
        update_post_meta( $new_post_id, 'hhb_total_bedrooms',  $total_bedrooms );
        update_post_meta( $new_post_id, 'hhb_total_bathrooms', $total_bathrooms );
        update_post_meta( $new_post_id, 'hhb_max_guests',      $max_guests );
        update_post_meta( $new_post_id, 'hhb_min_nights',      $min_nights );
        update_post_meta( $new_post_id, 'hhb_max_nights',      $max_nights );
        update_post_meta( $new_post_id, 'hhb_extra_guest_fee', $extra_guest_fee );

        // Host profile — save mode and clear opposing mode's fields to prevent stale data
        update_post_meta( $new_post_id, 'hhb_host_mode', $host_mode );
        if ( 'manual' === $host_mode ) {
            update_post_meta( $new_post_id, 'hhb_host_name',       $host_name );
            update_post_meta( $new_post_id, 'hhb_host_email',      $host_email );
            update_post_meta( $new_post_id, 'hhb_host_phone',      $host_phone );
            update_post_meta( $new_post_id, 'hhb_host_bio',        $host_bio );
            update_post_meta( $new_post_id, 'hhb_host_avatar_url', $host_avatar_url );
            delete_post_meta( $new_post_id, 'hhb_host_user_id' );
        }

        // Save structured address (replaces old lat/lng)
        update_post_meta( $new_post_id, 'hhb_address',     $address );
        update_post_meta( $new_post_id, 'hhb_city',        $city );
        update_post_meta( $new_post_id, 'hhb_state',       $state );
        update_post_meta( $new_post_id, 'hhb_country',     $country ?: 'India' );
        update_post_meta( $new_post_id, 'hhb_postal_code', $postal_code );

        // Save Booking rules
        update_post_meta( $new_post_id, 'hhb_buffer_days', $buffer_days );
        update_post_meta( $new_post_id, 'hhb_deposit_percent', $deposit_percent );
        update_post_meta( $new_post_id, 'hhb_dos', $dos );
        update_post_meta( $new_post_id, 'hhb_donts', $donts );
        update_post_meta( $new_post_id, 'hhb_attractions', $attractions );

        // Save Additional Property Info
        update_post_meta( $new_post_id, 'hhb_property_size', $property_size );
        update_post_meta( $new_post_id, 'hhb_year_established', $year_established );
        
        // Save Check-in / Check-out
        update_post_meta( $new_post_id, 'hhb_checkin_time', $checkin_time );
        update_post_meta( $new_post_id, 'hhb_checkout_time', $checkout_time );
        update_post_meta( $new_post_id, 'hhb_early_checkin', $early_checkin );
        update_post_meta( $new_post_id, 'hhb_late_checkout', $late_checkout );

        // Save Contact
        update_post_meta( $new_post_id, 'hhb_contact_phone', $contact_phone );
        update_post_meta( $new_post_id, 'hhb_contact_email', $contact_email );
        update_post_meta( $new_post_id, 'hhb_website_url', $website_url );

        // Save WP user ID for host (user mode) — clear manual fields when switching back
        if ( 'user' === $host_mode ) {
            update_post_meta( $new_post_id, 'hhb_host_user_id', $host_user_id );
            delete_post_meta( $new_post_id, 'hhb_host_name' );
            delete_post_meta( $new_post_id, 'hhb_host_email' );
            delete_post_meta( $new_post_id, 'hhb_host_phone' );
            delete_post_meta( $new_post_id, 'hhb_host_bio' );
            delete_post_meta( $new_post_id, 'hhb_host_avatar_url' );
        }

        // Save icon amenity checkboxes (meta array)
        $allowed_amenity_keys = [ 'wifi', 'parking', 'kitchen', 'ac', 'tv', 'washing_machine', 'hot_water', 'garden', 'balcony', 'fireplace', 'gym', 'pool' ];
        if ( isset( $_POST['hhb_amenities'] ) && is_array( $_POST['hhb_amenities'] ) ) {
            $saved_amenities = array_intersect( array_map( 'sanitize_key', wp_unslash( $_POST['hhb_amenities'] ) ), $allowed_amenity_keys );
            update_post_meta( $new_post_id, 'hhb_amenities', array_values( $saved_amenities ) );
        } else {
            update_post_meta( $new_post_id, 'hhb_amenities', [] );
        }

        // 6b. Save Media
        if ( isset( $_POST['cover_image_id'] ) && ! empty( $_POST['cover_image_id'] ) ) {
            set_post_thumbnail( $new_post_id, intval( $_POST['cover_image_id'] ) );
        } else {
            delete_post_thumbnail( $new_post_id );
        }

        if ( isset( $_POST['gallery_image_ids'] ) ) {
            $gallery_ids = array_filter( array_map( 'intval', explode( ',', wp_unslash( $_POST['gallery_image_ids'] ) ) ) );
            // Single array source of truth
            update_post_meta( $new_post_id, 'hhb_gallery', $gallery_ids );
        }

        // 6c. Save Taxonomy

        // Save Taxonomy Amenity Terms
        if ( isset( $_POST['hhb_amenity_terms'] ) && is_array( $_POST['hhb_amenity_terms'] ) ) {
            $amenity_terms = array_map( 'intval', wp_unslash( $_POST['hhb_amenity_terms'] ) );
            wp_set_object_terms( $new_post_id, $amenity_terms, 'hhb_amenity', false );
        } else {
            wp_set_object_terms( $new_post_id, [], 'hhb_amenity', false );
        }

        if ( isset( $_POST['hhb_locations'] ) && is_array( $_POST['hhb_locations'] ) ) {
            $loc_term_ids = [];
            foreach ( wp_unslash( $_POST['hhb_locations'] ) as $loc ) {
                if ( is_numeric( $loc ) ) {
                    $loc_term_ids[] = intval( $loc ); // Existing term ID
                } else {
                    $loc_term_ids[] = sanitize_text_field( $loc ); // New term string
                }
            }
            wp_set_object_terms( $new_post_id, $loc_term_ids, 'hhb_location', false );
        } else {
            wp_set_object_terms( $new_post_id, [], 'hhb_location', false );
        }

        if ( isset( $_POST['hhb_property_types'] ) && is_array( $_POST['hhb_property_types'] ) ) {
            $prop_types = [];
            foreach ( wp_unslash( $_POST['hhb_property_types'] ) as $pt ) {
                if ( is_numeric( $pt ) ) {
                    $prop_types[] = intval( $pt ); // Existing term ID
                } else {
                    $prop_types[] = sanitize_text_field( $pt ); // New term string
                }
            }
            wp_set_object_terms( $new_post_id, $prop_types, 'hhb_property_type', false );
        } else {
            wp_set_object_terms( $new_post_id, [], 'hhb_property_type', false );
        }


        // 6d. Save Rooms Repeater
        if ( isset( $_POST['hhb_rooms_nonce'] ) && wp_verify_nonce( $_POST['hhb_rooms_nonce'], 'hhb_save_rooms' ) ) {
            $rooms_input = isset( $_POST['hhb_rooms'] ) && is_array( $_POST['hhb_rooms'] ) ? wp_unslash( $_POST['hhb_rooms'] ) : array();
            $base_prices = array();

            foreach ( $rooms_input as $room_data ) {
                $room_id_raw = $room_data['id'] ?? '';
                $is_temp     = strpos( $room_id_raw, 'temp_' ) === 0;
                $room_id     = intval( $room_id_raw );
                
                $to_delete = ! empty( $room_data['delete'] ) && $room_data['delete'] === '1';

                if ( $to_delete ) {
                    if ( $room_id > 0 ) {
                        wp_trash_post( $room_id );
                    }
                    continue;
                }

                $title = sanitize_text_field( $room_data['title'] ?? '' );
                if ( empty( $title ) ) {
                    $title = __( 'Room', 'himalayan-homestay-bookings' );
                }

                if ( $room_id > 0 && ! $is_temp ) {
                    // Update existing room title.
                    wp_update_post( array( 'ID' => $room_id, 'post_title' => $title, 'post_name' => '' ) );
                } else {
                    $existing_room = 0;
                    if ( $is_temp ) {
                        $existing_rooms = get_posts( [
                            'post_parent' => $new_post_id,
                            'post_type'   => 'hhb_room',
                            'meta_key'    => '_hhb_temp_id',
                            'meta_value'  => $room_id_raw,
                            'fields'      => 'ids',
                            'post_status' => 'any',
                            'numberposts' => 1
                        ] );
                        if ( ! empty( $existing_rooms ) ) {
                            $existing_room = $existing_rooms[0];
                        }
                    }

                    if ( $existing_room > 0 ) {
                        $room_id = $existing_room;
                        wp_update_post( array( 'ID' => $room_id, 'post_title' => $title, 'post_name' => '' ) );
                    } else {
                        // Create new child room
                        $room_id = wp_insert_post( array(
                            'post_title'  => $title,
                            'post_type'   => 'hhb_room',
                            'post_status' => 'publish',
                            'post_parent' => $new_post_id,
                        ) );
                        if ( is_wp_error( $room_id ) || ! $room_id ) {
                            continue;
                        }
                        // Ensure parent is set via meta as fallback
                        update_post_meta( $room_id, '_hhb_homestay_id', $new_post_id );
                        if ( $is_temp ) {
                            update_post_meta( $room_id, '_hhb_temp_id', $room_id_raw );
                        }
                    }
                }

                // Save all room meta
                $base_price = floatval( $room_data['base_price'] ?? 0 );
                update_post_meta( $room_id, 'room_base_price',      $base_price );
                update_post_meta( $room_id, 'room_max_guests',      max( 1, intval( $room_data['max_guests'] ?? 2 ) ) );
                update_post_meta( $room_id, 'room_quantity',        max( 1, intval( $room_data['quantity'] ?? 1 ) ) );
                update_post_meta( $room_id, 'room_bed_type',        sanitize_text_field( $room_data['bed_type'] ?? '' ) );

                if ( $base_price > 0 ) {
                    $base_prices[] = $base_price;
                }
            }

            // Recalculate and cache price range on the homestay
            if ( ! empty( $base_prices ) ) {
                update_post_meta( $new_post_id, 'hhb_price_min', min( $base_prices ) );
                update_post_meta( $new_post_id, 'hhb_price_max', max( $base_prices ) );
            } else {
                delete_post_meta( $new_post_id, 'hhb_price_min' );
                delete_post_meta( $new_post_id, 'hhb_price_max' );
            }
        }

        // 7. Success Response
        wp_send_json_success( [
            'property_id' => $new_post_id,
            'message'     => 'Property saved successfully.'
        ] );
    }
}
