<?php
namespace Himalayan\Homestay\Interface\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class RoomMetaBoxes {

    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
        add_action( 'save_post_hhb_room', array( __CLASS__, 'save_meta_boxes' ) );
    }

    public static function add_meta_boxes() {
        add_meta_box( 
            'hhb_room_details', 
            __( 'Room Details, Capacity & Pricing', 'himalayan-homestay-bookings' ), 
            array( __CLASS__, 'render_details_meta_box' ), 
            'hhb_room', 
            'normal', 
            'high' 
        );
    }

    public static function render_details_meta_box( $post ) {
        wp_nonce_field( 'hhb_save_room_details', 'hhb_room_details_nonce' );
        
        // Pricing fields
        $base_price       = get_post_meta( $post->ID, 'room_base_price', true );
        $weekend_price    = get_post_meta( $post->ID, 'room_weekend_price', true );
        $extra_guest_fee  = get_post_meta( $post->ID, 'room_extra_guest_fee', true );
        
        // Capacity fields
        $max_guests       = get_post_meta( $post->ID, 'room_max_guests', true ) ?: 2;
        
        // Inventory fields
        $quantity         = get_post_meta( $post->ID, 'room_quantity', true ) ?: 1;
        
        // Configuration fields
        $min_nights       = get_post_meta( $post->ID, 'room_min_nights', true ) ?: 1;
        $max_nights       = get_post_meta( $post->ID, 'room_max_nights', true ) ?: 30;
        $size_sqft        = get_post_meta( $post->ID, 'room_size_sqft', true );
        $bed_type         = get_post_meta( $post->ID, 'room_bed_type', true );
        ?>
        <div class="hhb-meta-container" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="hhb-field-group">
                <h4 style="margin-bottom:8px; border-bottom:1px solid #ddd; padding-bottom:5px;">Pricing</h4>
                <div class="hhb-field" style="margin-bottom:10px;">
                    <label style="display:block; font-weight:bold; margin-bottom:4px;">Base Price / Night</label>
                    <input type="number" name="room_base_price" value="<?php echo esc_attr($base_price); ?>" step="0.01" style="width:100%;">
                </div>
                <div class="hhb-field" style="margin-bottom:10px;">
                    <label style="display:block; font-weight:bold; margin-bottom:4px;">Weekend Price / Night (Optional)</label>
                    <input type="number" name="room_weekend_price" value="<?php echo esc_attr($weekend_price); ?>" step="0.01" style="width:100%;">
                </div>
                <div class="hhb-field" style="margin-bottom:10px;">
                    <label style="display:block; font-weight:bold; margin-bottom:4px;">Extra Guest Fee / Night</label>
                    <input type="number" name="room_extra_guest_fee" value="<?php echo esc_attr($extra_guest_fee); ?>" step="0.01" style="width:100%;">
                    <p class="description" style="margin-top:2px;">Charged per guest beyond the max included guests.</p>
                </div>
            </div>

            <div class="hhb-field-group">
                <h4 style="margin-bottom:8px; border-bottom:1px solid #ddd; padding-bottom:5px;">Capacity & Inventory</h4>
                <div class="hhb-field" style="margin-bottom:10px;">
                    <label style="display:block; font-weight:bold; margin-bottom:4px;">Max Guests Included</label>
                    <input type="number" name="room_max_guests" value="<?php echo esc_attr($max_guests); ?>" min="1" style="width:100%;">
                </div>
                <div class="hhb-field" style="margin-bottom:10px;">
                    <label style="display:block; font-weight:bold; margin-bottom:4px;">Room Quantity</label>
                    <input type="number" name="room_quantity" value="<?php echo esc_attr($quantity); ?>" min="1" style="width:100%;">
                    <p class="description" style="margin-top:2px;">How many rooms of this exact type exist?</p>
                </div>
            </div>

            <div class="hhb-field-group" style="grid-column: 1 / -1;">
                <h4 style="margin-bottom:8px; border-bottom:1px solid #ddd; padding-bottom:5px;">Configuration</h4>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
                    <div class="hhb-field">
                        <label style="display:block; font-weight:bold; margin-bottom:4px;">Min Nights</label>
                        <input type="number" name="room_min_nights" value="<?php echo esc_attr($min_nights); ?>" min="1" style="width:100%;">
                    </div>
                    <div class="hhb-field">
                        <label style="display:block; font-weight:bold; margin-bottom:4px;">Max Nights</label>
                        <input type="number" name="room_max_nights" value="<?php echo esc_attr($max_nights); ?>" min="1" style="width:100%;">
                    </div>
                    <div class="hhb-field">
                        <label style="display:block; font-weight:bold; margin-bottom:4px;">Room Size (sq. ft.)</label>
                        <input type="text" name="room_size_sqft" value="<?php echo esc_attr($size_sqft); ?>" style="width:100%;">
                    </div>
                    <div class="hhb-field">
                        <label style="display:block; font-weight:bold; margin-bottom:4px;">Bed Type (e.g. 1 King)</label>
                        <input type="text" name="room_bed_type" value="<?php echo esc_attr($bed_type); ?>" style="width:100%;">
                    </div>
                </div>
            </div>
            
        </div>
        <?php
    }

    public static function save_meta_boxes( $post_id ) {
        if ( ! isset( $_POST['hhb_room_details_nonce'] ) || ! wp_verify_nonce( $_POST['hhb_room_details_nonce'], 'hhb_save_room_details' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        $fields = [
            'room_base_price',
            'room_weekend_price',
            'room_extra_guest_fee',
            'room_max_guests',
            'room_quantity',
            'room_min_nights',
            'room_max_nights',
            'room_size_sqft',
            'room_bed_type'
        ];

        foreach ( $fields as $field ) {
            if ( isset( $_POST[$field] ) ) {
                $sanitized = sanitize_text_field( wp_unslash( $_POST[$field] ) );
                update_post_meta( $post_id, $field, $sanitized );
            } else {
                delete_post_meta( $post_id, $field );
            }
        }
    }
}
