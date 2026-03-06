<?php
namespace Himalayan\Homestay\Interface\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class HomestayMetaBoxes {

    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
        add_action( 'save_post_hhb_homestay', array( __CLASS__, 'save_meta_boxes' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
    }

    public static function enqueue_admin_assets($hook) {
        if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) return;
        if ( get_post_type() !== 'hhb_homestay' ) return;

        wp_enqueue_media(); // For gallery image selector
        wp_enqueue_style( 'hhb-admin-css', plugin_dir_url( dirname( __DIR__ ) ) . 'assets/admin.css' );
        wp_enqueue_script( 'hhb-admin-js', plugin_dir_url( dirname( __DIR__ ) ) . 'assets/admin.js', array('jquery'), time(), true );
    }

    public static function add_meta_boxes() {
        add_meta_box( 'hhb_homestay_gallery', __( 'Property Images (Gallery)', 'himalayan-homestay-bookings' ), array( __CLASS__, 'render_gallery_meta_box' ), 'hhb_homestay', 'normal', 'high' );
        add_meta_box( 'hhb_homestay_rooms', __( 'Rooms', 'himalayan-homestay-bookings' ), array( __CLASS__, 'render_rooms_meta_box' ), 'hhb_homestay', 'normal', 'high' );
        add_meta_box( 'hhb_homestay_details', __( 'Homestay Details', 'himalayan-homestay-bookings' ), array( __CLASS__, 'render_details_meta_box' ), 'hhb_homestay', 'normal', 'high' );
        add_meta_box( 'hhb_homestay_amenities', __( 'Property Amenities', 'himalayan-homestay-bookings' ), array( __CLASS__, 'render_amenities_meta_box' ), 'hhb_homestay', 'normal', 'high' );
        add_meta_box( 'hhb_homestay_rules', __( 'House Rules (Dos & Don\'ts)', 'himalayan-homestay-bookings' ), array( __CLASS__, 'render_rules_meta_box' ), 'hhb_homestay', 'normal', 'default' );
        add_meta_box( 'hhb_homestay_attractions', __( 'Nearby Attractions', 'himalayan-homestay-bookings' ), array( __CLASS__, 'render_attractions_meta_box' ), 'hhb_homestay', 'normal', 'default' );
        add_meta_box( 'hhb_homestay_pricing_rules', __( 'Advanced Pricing Rules (Seasonal/Weekend)', 'himalayan-homestay-bookings' ), array( __CLASS__, 'render_pricing_rules_meta_box' ), 'hhb_homestay', 'normal', 'default' );
        add_meta_box( 'hhb_homestay_booking_rules', __( 'Booking Rules', 'himalayan-homestay-bookings' ), array( __CLASS__, 'render_booking_rules_meta_box' ), 'hhb_homestay', 'side', 'default' );
        add_meta_box( 'hhb_homestay_extra_services', __( 'Extra Services & Add-ons', 'himalayan-homestay-bookings' ), array( __CLASS__, 'render_extra_services_meta_box' ), 'hhb_homestay', 'normal', 'default' );
        add_meta_box( 'hhb_homestay_ical_feeds', __( 'iCal Sync (Channel Manager)', 'himalayan-homestay-bookings' ), array( __CLASS__, 'render_ical_feeds_meta_box' ), 'hhb_homestay', 'normal', 'default' );
        add_meta_box( 'hhb_homestay_host', __( 'Host Profile', 'himalayan-homestay-bookings' ), array( __CLASS__, 'render_host_meta_box' ), 'hhb_homestay', 'side', 'high' );
    }

    public static function render_rooms_meta_box( $post ) {
        wp_nonce_field( 'hhb_save_rooms', 'hhb_rooms_nonce' );

        $rooms = get_posts( array(
            'post_type'      => 'hhb_room',
            'post_parent'    => $post->ID,
            'posts_per_page' => -1,
            'post_status'    => array( 'publish', 'draft' ),
            'orderby'        => 'date',
            'order'          => 'ASC',
        ) );

        $room_fields_html = function( $index, $data ) {
            $f = $data;
            ob_start();
            ?>
            <div class="hhb-room-row" style="border:1px solid #c3c4c7; border-radius:4px; padding:15px; margin-bottom:12px; background:#fafafa;">
                <input type="hidden" name="hhb_rooms[<?php echo $index; ?>][id]" value="<?php echo esc_attr( $f['id'] ); ?>">
                <input type="hidden" name="hhb_rooms[<?php echo $index; ?>][delete]" class="hhb-room-delete-flag" value="0">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <strong style="font-size:13px; color:#1d2327; margin-right:8px;">Room Name:</strong>
                    <input type="text" name="hhb_rooms[<?php echo $index; ?>][title]" value="<?php echo esc_attr( $f['title'] ); ?>" placeholder="e.g. Deluxe Double Room" style="flex:1; font-size:13px; font-weight:600; margin-right:12px;">
                    <button type="button" class="button hhb-remove-room" style="color:#b32d2e; border-color:#b32d2e;">&#10005; Remove</button>
                </div>
                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:10px;">
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; margin-bottom:3px;">Base Price / Night (₹) *</label>
                        <input type="number" name="hhb_rooms[<?php echo $index; ?>][base_price]" value="<?php echo esc_attr( $f['base_price'] ); ?>" step="0.01" min="0" placeholder="0.00" style="width:100%;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; margin-bottom:3px;">Weekend Price / Night (₹)</label>
                        <input type="number" name="hhb_rooms[<?php echo $index; ?>][weekend_price]" value="<?php echo esc_attr( $f['weekend_price'] ); ?>" step="0.01" min="0" placeholder="Optional" style="width:100%;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; margin-bottom:3px;">Extra Guest Fee / Night (₹)</label>
                        <input type="number" name="hhb_rooms[<?php echo $index; ?>][extra_guest_fee]" value="<?php echo esc_attr( $f['extra_guest_fee'] ); ?>" step="0.01" min="0" placeholder="0.00" style="width:100%;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; margin-bottom:3px;">Max Guests</label>
                        <input type="number" name="hhb_rooms[<?php echo $index; ?>][max_guests]" value="<?php echo esc_attr( $f['max_guests'] ); ?>" min="1" style="width:100%;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; margin-bottom:3px;">Room Quantity</label>
                        <input type="number" name="hhb_rooms[<?php echo $index; ?>][quantity]" value="<?php echo esc_attr( $f['quantity'] ); ?>" min="1" style="width:100%;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; margin-bottom:3px;">Bed Type (e.g. 1 King)</label>
                        <input type="text" name="hhb_rooms[<?php echo $index; ?>][bed_type]" value="<?php echo esc_attr( $f['bed_type'] ); ?>" placeholder="e.g. 1 King" style="width:100%;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; margin-bottom:3px;">Min Nights</label>
                        <input type="number" name="hhb_rooms[<?php echo $index; ?>][min_nights]" value="<?php echo esc_attr( $f['min_nights'] ); ?>" min="1" style="width:100%;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; margin-bottom:3px;">Max Nights</label>
                        <input type="number" name="hhb_rooms[<?php echo $index; ?>][max_nights]" value="<?php echo esc_attr( $f['max_nights'] ); ?>" min="1" style="width:100%;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; margin-bottom:3px;">Room Size (sq.ft)</label>
                        <input type="text" name="hhb_rooms[<?php echo $index; ?>][size_sqft]" value="<?php echo esc_attr( $f['size_sqft'] ); ?>" placeholder="e.g. 250" style="width:100%;">
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        };
        ?>
        <div id="hhb-rooms-container">
            <?php
            foreach ( $rooms as $index => $room ) {
                echo $room_fields_html( $index, array(
                    'id'              => $room->ID,
                    'title'           => $room->post_title,
                    'base_price'      => get_post_meta( $room->ID, 'room_base_price', true ),
                    'weekend_price'   => get_post_meta( $room->ID, 'room_weekend_price', true ),
                    'extra_guest_fee' => get_post_meta( $room->ID, 'room_extra_guest_fee', true ),
                    'max_guests'      => get_post_meta( $room->ID, 'room_max_guests', true ) ?: 2,
                    'quantity'        => get_post_meta( $room->ID, 'room_quantity', true ) ?: 1,
                    'min_nights'      => get_post_meta( $room->ID, 'room_min_nights', true ) ?: 1,
                    'max_nights'      => get_post_meta( $room->ID, 'room_max_nights', true ) ?: 30,
                    'size_sqft'       => get_post_meta( $room->ID, 'room_size_sqft', true ),
                    'bed_type'        => get_post_meta( $room->ID, 'room_bed_type', true ),
                ) );
            }
            ?>
        </div>
        <button type="button" class="button button-primary" id="hhb-add-room" style="margin-top:4px;">+ Add Room</button>

        <script type="text/html" id="hhb-room-template">
            <div class="hhb-room-row" style="border:1px solid #c3c4c7; border-radius:4px; padding:15px; margin-bottom:12px; background:#fafafa;">
                <input type="hidden" name="hhb_rooms[__IDX__][id]" value="0">
                <input type="hidden" name="hhb_rooms[__IDX__][delete]" class="hhb-room-delete-flag" value="0">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <strong style="font-size:13px; color:#1d2327; margin-right:8px;">Room Name:</strong>
                    <input type="text" name="hhb_rooms[__IDX__][title]" value="" placeholder="e.g. Deluxe Double Room" style="flex:1; font-size:13px; font-weight:600; margin-right:12px;">
                    <button type="button" class="button hhb-remove-room" style="color:#b32d2e; border-color:#b32d2e;">&#10005; Remove</button>
                </div>
                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:10px;">
                    <div><label style="display:block; font-size:11px; font-weight:600; margin-bottom:3px;">Base Price / Night (₹) *</label><input type="number" name="hhb_rooms[__IDX__][base_price]" value="" step="0.01" min="0" placeholder="0.00" style="width:100%;"></div>
                    <div><label style="display:block; font-size:11px; font-weight:600; margin-bottom:3px;">Weekend Price / Night (₹)</label><input type="number" name="hhb_rooms[__IDX__][weekend_price]" value="" step="0.01" min="0" placeholder="Optional" style="width:100%;"></div>
                    <div><label style="display:block; font-size:11px; font-weight:600; margin-bottom:3px;">Extra Guest Fee / Night (₹)</label><input type="number" name="hhb_rooms[__IDX__][extra_guest_fee]" value="" step="0.01" min="0" placeholder="0.00" style="width:100%;"></div>
                    <div><label style="display:block; font-size:11px; font-weight:600; margin-bottom:3px;">Max Guests</label><input type="number" name="hhb_rooms[__IDX__][max_guests]" value="2" min="1" style="width:100%;"></div>
                    <div><label style="display:block; font-size:11px; font-weight:600; margin-bottom:3px;">Room Quantity</label><input type="number" name="hhb_rooms[__IDX__][quantity]" value="1" min="1" style="width:100%;"></div>
                    <div><label style="display:block; font-size:11px; font-weight:600; margin-bottom:3px;">Bed Type (e.g. 1 King)</label><input type="text" name="hhb_rooms[__IDX__][bed_type]" value="" placeholder="e.g. 1 King" style="width:100%;"></div>
                    <div><label style="display:block; font-size:11px; font-weight:600; margin-bottom:3px;">Min Nights</label><input type="number" name="hhb_rooms[__IDX__][min_nights]" value="1" min="1" style="width:100%;"></div>
                    <div><label style="display:block; font-size:11px; font-weight:600; margin-bottom:3px;">Max Nights</label><input type="number" name="hhb_rooms[__IDX__][max_nights]" value="30" min="1" style="width:100%;"></div>
                    <div><label style="display:block; font-size:11px; font-weight:600; margin-bottom:3px;">Room Size (sq.ft)</label><input type="text" name="hhb_rooms[__IDX__][size_sqft]" value="" placeholder="e.g. 250" style="width:100%;"></div>
                </div>
            </div>
        </script>
        <script>
        (function($) {
            var hhbRoomIdx = <?php echo count( $rooms ); ?>;

            $('#hhb-add-room').on('click', function() {
                var html = $('#hhb-room-template').html().replace(/__IDX__/g, hhbRoomIdx);
                $('#hhb-rooms-container').append(html);
                hhbRoomIdx++;
            });

            $(document).on('click', '.hhb-remove-room', function() {
                var $row = $(this).closest('.hhb-room-row');
                var roomId = $row.find('input[name$="[id]"]').val();
                if ( roomId && roomId !== '0' ) {
                    $row.find('.hhb-room-delete-flag').val('1');
                    $row.slideUp(200);
                } else {
                    $row.remove();
                }
            });
        })(jQuery);
        </script>
        <?php
    }

    public static function render_gallery_meta_box( $post ) {
        $gallery_ids = get_post_meta($post->ID, 'hhb_gallery', true);
        $gallery_ids = $gallery_ids ? explode(',', $gallery_ids) : array();
        ?>
        <div class="homestay-gallery-wrapper">
            <div class="homestay-gallery-images" id="homestay-gallery-container" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:10px;">
                <?php if (!empty($gallery_ids)) : ?>
                    <?php foreach ($gallery_ids as $image_id) : ?>
                        <?php $image_url = wp_get_attachment_image_url($image_id, 'thumbnail'); ?>
                        <div class="gallery-image-item" data-id="<?php echo esc_attr($image_id); ?>" style="position:relative; width:100px; height:100px; border:1px solid #ddd;">
                            <img src="<?php echo esc_url($image_url); ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                            <button type="button" class="remove-gallery-image" style="position:absolute; top:2px; right:2px; background:red; color:white; border:none; cursor:pointer; font-weight:bold;">&times;</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" class="button button-primary" id="add-gallery-images">
                <?php _e('Add Images', 'himalayan-homestay-bookings'); ?>
            </button>
            <input type="hidden" name="hhb_gallery" id="homestay-gallery-ids" value="<?php echo esc_attr(implode(',', $gallery_ids)); ?>">
            <p class="description"><?php _e('First image will be used as the hero image. Drag to reorder.', 'himalayan-homestay-bookings'); ?></p>
        </div>
        <script>
            // Inline fallback for gallery JS if homestay-admin.js isn't perfectly mapped
            jQuery(document).ready(function($) {
                var frame;
                $('#add-gallery-images').on('click', function(e) {
                    e.preventDefault();
                    if (frame) { frame.open(); return; }
                    frame = wp.media({ title: 'Select Images', button: { text: 'Use these images' }, multiple: true });
                    frame.on('select', function() {
                        var selection = frame.state().get('selection');
                        selection.map(function(attachment) {
                            attachment = attachment.toJSON();
                            var url = attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                            $('#homestay-gallery-container').append('<div class="gallery-image-item" data-id="'+attachment.id+'" style="position:relative; width:100px; height:100px; border:1px solid #ddd;"><img src="'+url+'" style="width:100%; height:100%; object-fit:cover;"><button type="button" class="remove-gallery-image" style="position:absolute; top:2px; right:2px; background:red; color:white; border:none; cursor:pointer; font-weight:bold;">&times;</button></div>');
                        });
                        updateGalleryInput();
                    });
                    frame.open();
                });
                $('#homestay-gallery-container').on('click', '.remove-gallery-image', function() {
                    $(this).closest('.gallery-image-item').remove();
                    updateGalleryInput();
                });
                function updateGalleryInput() {
                    var ids = [];
                    $('#homestay-gallery-container .gallery-image-item').each(function() { ids.push($(this).data('id')); });
                    $('#homestay-gallery-ids').val(ids.join(','));
                }
            });
        </script>
        <?php
    }

    public static function render_details_meta_box( $post ) {
        wp_nonce_field( 'hhb_save_homestay_details', 'hhb_homestay_details_nonce' );

        // Location
        $address       = get_post_meta( $post->ID, 'hhb_address', true );
        $city          = get_post_meta( $post->ID, 'hhb_city', true );
        $state         = get_post_meta( $post->ID, 'hhb_state', true );
        $country       = get_post_meta( $post->ID, 'hhb_country', true );
        $postal_code   = get_post_meta( $post->ID, 'hhb_postal_code', true );

        // Property Info
        $property_type    = get_post_meta( $post->ID, 'hhb_property_type', true );
        $total_bedrooms   = get_post_meta( $post->ID, 'hhb_total_bedrooms', true );
        $total_bathrooms  = get_post_meta( $post->ID, 'hhb_total_bathrooms', true );
        $max_guests       = get_post_meta( $post->ID, 'hhb_max_guests', true );
        $property_size    = get_post_meta( $post->ID, 'hhb_property_size', true );
        $year_established = get_post_meta( $post->ID, 'hhb_year_established', true );

        // Check-in / Check-out
        $checkin_time    = get_post_meta( $post->ID, 'hhb_checkin_time', true )  ?: '14:00';
        $checkout_time   = get_post_meta( $post->ID, 'hhb_checkout_time', true ) ?: '11:00';
        $early_checkin   = get_post_meta( $post->ID, 'hhb_early_checkin', true );
        $late_checkout   = get_post_meta( $post->ID, 'hhb_late_checkout', true );

        // Contact
        $contact_phone = get_post_meta( $post->ID, 'hhb_contact_phone', true );
        $contact_email = get_post_meta( $post->ID, 'hhb_contact_email', true );
        $website_url   = get_post_meta( $post->ID, 'hhb_website_url', true );

        $property_types = array(
            ''            => '— Select Type —',
            'homestay'    => 'Homestay',
            'villa'       => 'Villa',
            'cottage'     => 'Cottage',
            'farmhouse'   => 'Farmhouse',
            'guesthouse'  => 'Guesthouse',
            'apartment'   => 'Apartment',
            'treehouse'   => 'Treehouse',
            'resort'      => 'Resort / Hotel',
            'camp'        => 'Camp / Tent',
            'other'       => 'Other',
        );
        ?>
        <style>
            .hhb-details-section { margin-bottom: 24px; }
            .hhb-details-section h4 {
                margin: 0 0 12px;
                padding: 6px 10px;
                background: #f0f0f1;
                border-left: 3px solid #2271b1;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: .5px;
                color: #1d2327;
            }
            .hhb-details-grid { display: grid; gap: 14px; }
            .hhb-details-grid.cols-2 { grid-template-columns: 1fr 1fr; }
            .hhb-details-grid.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
            .hhb-details-grid.cols-4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
            .hhb-details-grid.cols-5 { grid-template-columns: 2fr 1fr 1fr 1fr 1fr; }
            .hhb-d-field label {
                display: block;
                font-size: 11px;
                font-weight: 700;
                color: #50575e;
                margin-bottom: 4px;
                text-transform: uppercase;
                letter-spacing: .3px;
            }
            .hhb-d-field input[type="text"],
            .hhb-d-field input[type="email"],
            .hhb-d-field input[type="url"],
            .hhb-d-field input[type="number"],
            .hhb-d-field input[type="time"],
            .hhb-d-field select { width: 100%; }
            .hhb-d-field .description { margin-top: 3px; font-size: 11px; color: #8c8f94; }
            .hhb-toggle-row { display: flex; gap: 20px; align-items: center; margin-top: 4px; }
            .hhb-toggle-row label { display: flex; align-items: center; gap: 6px; font-weight: 600; font-size: 12px; cursor: pointer; }
            .hhb-info-note { background: #e8f4fd; border-left: 3px solid #2271b1; padding: 8px 12px; font-size: 12px; color: #2271b1; margin-bottom: 18px; border-radius: 0 3px 3px 0; }
        </style>

        <p class="hhb-info-note">Room-level pricing and capacity is managed in the <strong>Rooms</strong> metabox above. Fill in property-wide details here.</p>

        <!-- ── LOCATION ─────────────────────────────────────────── -->
        <div class="hhb-details-section">
            <h4>Location</h4>
            <div class="hhb-details-grid cols-5">
                <div class="hhb-d-field">
                    <label for="hhb_address">Street Address *</label>
                    <input type="text" id="hhb_address" name="hhb_address" value="<?php echo esc_attr( $address ); ?>" placeholder="e.g. 12 Hill View Road">
                </div>
                <div class="hhb-d-field">
                    <label for="hhb_city">City / Village *</label>
                    <input type="text" id="hhb_city" name="hhb_city" value="<?php echo esc_attr( $city ); ?>" placeholder="e.g. Manali">
                </div>
                <div class="hhb-d-field">
                    <label for="hhb_state">State *</label>
                    <input type="text" id="hhb_state" name="hhb_state" value="<?php echo esc_attr( $state ); ?>" placeholder="e.g. Himachal Pradesh">
                </div>
                <div class="hhb-d-field">
                    <label for="hhb_country">Country</label>
                    <input type="text" id="hhb_country" name="hhb_country" value="<?php echo esc_attr( $country ?: 'India' ); ?>" placeholder="India">
                </div>
                <div class="hhb-d-field">
                    <label for="hhb_postal_code">PIN / Postal Code</label>
                    <input type="text" id="hhb_postal_code" name="hhb_postal_code" value="<?php echo esc_attr( $postal_code ); ?>" placeholder="e.g. 175131">
                </div>
            </div>
        </div>

        <!-- ── PROPERTY INFO ─────────────────────────────────────── -->
        <div class="hhb-details-section">
            <h4>Property Info</h4>
            <div class="hhb-details-grid cols-3">
                <div class="hhb-d-field">
                    <label for="hhb_property_type">Property Type *</label>
                    <select id="hhb_property_type" name="hhb_property_type">
                        <?php foreach ( $property_types as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $property_type, $val ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="hhb-d-field">
                    <label for="hhb_total_bedrooms">Total Bedrooms</label>
                    <input type="number" id="hhb_total_bedrooms" name="hhb_total_bedrooms" value="<?php echo esc_attr( $total_bedrooms ); ?>" min="0" placeholder="e.g. 4">
                </div>
                <div class="hhb-d-field">
                    <label for="hhb_total_bathrooms">Total Bathrooms</label>
                    <input type="number" id="hhb_total_bathrooms" name="hhb_total_bathrooms" value="<?php echo esc_attr( $total_bathrooms ); ?>" min="0" placeholder="e.g. 2">
                </div>
                <div class="hhb-d-field">
                    <label for="hhb_max_guests">Max Guests (Property-wide)</label>
                    <input type="number" id="hhb_max_guests" name="hhb_max_guests" value="<?php echo esc_attr( $max_guests ); ?>" min="1" placeholder="e.g. 10">
                    <p class="description">Total across all rooms.</p>
                </div>
                <div class="hhb-d-field">
                    <label for="hhb_property_size">Property Size (sq.ft)</label>
                    <input type="number" id="hhb_property_size" name="hhb_property_size" value="<?php echo esc_attr( $property_size ); ?>" min="0" placeholder="e.g. 2500">
                </div>
                <div class="hhb-d-field">
                    <label for="hhb_year_established">Year Established</label>
                    <input type="number" id="hhb_year_established" name="hhb_year_established" value="<?php echo esc_attr( $year_established ); ?>" min="1900" max="<?php echo date('Y'); ?>" placeholder="e.g. 2015">
                </div>
            </div>
        </div>

        <!-- ── CHECK-IN / CHECK-OUT ──────────────────────────────── -->
        <div class="hhb-details-section">
            <h4>Check-in &amp; Check-out Policy</h4>
            <div class="hhb-details-grid cols-4">
                <div class="hhb-d-field">
                    <label for="hhb_checkin_time">Check-in Time</label>
                    <input type="time" id="hhb_checkin_time" name="hhb_checkin_time" value="<?php echo esc_attr( $checkin_time ); ?>">
                </div>
                <div class="hhb-d-field">
                    <label for="hhb_checkout_time">Check-out Time</label>
                    <input type="time" id="hhb_checkout_time" name="hhb_checkout_time" value="<?php echo esc_attr( $checkout_time ); ?>">
                </div>
                <div class="hhb-d-field">
                    <label>Early Check-in Available?</label>
                    <div class="hhb-toggle-row">
                        <label><input type="radio" name="hhb_early_checkin" value="yes" <?php checked( $early_checkin, 'yes' ); ?>> Yes</label>
                        <label><input type="radio" name="hhb_early_checkin" value="no"  <?php checked( $early_checkin, 'no' ); ?>>  No</label>
                    </div>
                </div>
                <div class="hhb-d-field">
                    <label>Late Check-out Available?</label>
                    <div class="hhb-toggle-row">
                        <label><input type="radio" name="hhb_late_checkout" value="yes" <?php checked( $late_checkout, 'yes' ); ?>> Yes</label>
                        <label><input type="radio" name="hhb_late_checkout" value="no"  <?php checked( $late_checkout, 'no' ); ?>>  No</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── CONTACT ──────────────────────────────────────────── -->
        <div class="hhb-details-section">
            <h4>Contact &amp; Links</h4>
            <div class="hhb-details-grid cols-3">
                <div class="hhb-d-field">
                    <label for="hhb_contact_phone">Contact Phone</label>
                    <input type="text" id="hhb_contact_phone" name="hhb_contact_phone" value="<?php echo esc_attr( $contact_phone ); ?>" placeholder="e.g. +91 98765 43210">
                </div>
                <div class="hhb-d-field">
                    <label for="hhb_contact_email">Contact Email</label>
                    <input type="email" id="hhb_contact_email" name="hhb_contact_email" value="<?php echo esc_attr( $contact_email ); ?>" placeholder="e.g. host@example.com">
                </div>
                <div class="hhb-d-field">
                    <label for="hhb_website_url">Website / Social Link</label>
                    <input type="url" id="hhb_website_url" name="hhb_website_url" value="<?php echo esc_attr( $website_url ); ?>" placeholder="https://...">
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_amenities_meta_box( $post ) {
        $saved_amenities = get_post_meta( $post->ID, 'hhb_amenities', true );
        $saved_amenities = $saved_amenities ? (array) $saved_amenities : array();

        $all_amenities = array(
            'wifi'            => 'WiFi',
            'parking'         => 'Free Parking',
            'kitchen'         => 'Kitchen',
            'ac'              => 'Air Conditioning',
            'tv'              => 'TV',
            'washing_machine' => 'Washing Machine',
            'hot_water'       => 'Hot Water',
            'garden'          => 'Garden',
            'balcony'         => 'Balcony',
            'fireplace'       => 'Fireplace',
            'gym'             => 'Gym',
            'pool'            => 'Swimming Pool',
        );

        $icons_dir = plugin_dir_path( dirname( __DIR__ ) ) . 'assets/icons/amenities/';
        ?>
        <style>
            .hhb-amenities-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
            }
            .hhb-amenity-item {
                display: flex !important;
                align-items: center;
                gap: 6px;
                padding: 6px 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                cursor: pointer;
                font-size: 13px;
                transition: background 0.15s;
            }
            .hhb-amenity-item:hover {
                background: #f0f6fc;
                border-color: #2271b1;
            }
            .hhb-amenity-item input[type="checkbox"] {
                margin: 0;
            }
            .hhb-amenity-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 15px;
                height: 15px;
                flex-shrink: 0;
            }
            .hhb-amenity-icon svg {
                width: 15px !important;
                height: 15px !important;
                max-width: 15px !important;
                max-height: 15px !important;
                display: block;
            }
            .hhb-amenity-label {
                font-size: 12px;
                line-height: 1.2;
            }
        </style>
        <div class="hhb-amenities-grid">
            <?php foreach ( $all_amenities as $key => $label ) :
                $svg_html = '';
                $svg_file = $icons_dir . $key . '.svg';
                if ( file_exists( $svg_file ) ) {
                    $svg_html = file_get_contents( $svg_file );
                } elseif ( $key === 'tv' ) {
                    $svg_html = '<span class="dashicons dashicons-desktop"></span>';
                }
            ?>
                <label class="hhb-amenity-item">
                    <input type="checkbox" name="hhb_amenities[]" value="<?php echo esc_attr($key); ?>" <?php checked( in_array( $key, $saved_amenities ) ); ?>>
                    <span class="hhb-amenity-icon"><?php echo $svg_html; ?></span>
                    <span class="hhb-amenity-label"><?php echo esc_html($label); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public static function render_rules_meta_box( $post ) {
        $dos = get_post_meta( $post->ID, 'hhb_dos', true );
        $donts = get_post_meta( $post->ID, 'hhb_donts', true );
        ?>
        <div class="hhb-meta-container">
            <div class="hhb-field">
                <label>The Dos (House Rules)</label>
                <textarea name="hhb_dos" rows="5" style="width:100%;"><?php echo esc_textarea($dos); ?></textarea>
            </div>
            <div class="hhb-field">
                <label>The Don'ts (Strictly Prohibited)</label>
                <textarea name="hhb_donts" rows="5" style="width:100%;"><?php echo esc_textarea($donts); ?></textarea>
            </div>
        </div>
        <?php
    }

    public static function render_attractions_meta_box( $post ) {
        $attractions = get_post_meta( $post->ID, 'hhb_attractions', true ) ?: array();
        ?>
        <div id="hhb-attractions-list">
            <?php foreach ( $attractions as $index => $val ) : ?>
                <div class="hhb-attraction-row" style="margin-bottom:10px; display:flex; gap:10px;">
                    <input type="text" name="hhb_attractions[]" value="<?php echo esc_attr($val); ?>" style="flex:1;" placeholder="e.g. Mall Road (2km)">
                    <button type="button" class="button remove-row">Remove</button>
                </div>
            <?php endforeach; ?>
            <div class="hhb-attraction-row" style="margin-bottom:10px; display:flex; gap:10px;">
                <input type="text" name="hhb_attractions[]" value="" style="flex:1;" placeholder="e.g. Waterfall (5km)">
            </div>
        </div>
        <button type="button" class="button add-attraction">Add More</button>
        <script>
            document.querySelector('.add-attraction').addEventListener('click', function() {
                const container = document.getElementById('hhb-attractions-list');
                const div = document.createElement('div');
                div.className = 'hhb-attraction-row';
                div.style = 'margin-bottom:10px; display:flex; gap:10px;';
                div.innerHTML = '<input type="text" name="hhb_attractions[]" value="" style="flex:1;"><button type="button" class="button remove-row">Remove</button>';
                container.appendChild(div);
            });
            document.addEventListener('click', function(e) {
                if(e.target.classList.contains('remove-row')) e.target.closest('.hhb-attraction-row').remove();
            });
        </script>
        <?php
    }

    public static function render_pricing_rules_meta_box( $post ) {
        global $wpdb;
        $table = $wpdb->prefix . 'himalayan_pricing_rules';
        $rules = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE homestay_id = %d", $post->ID ) );
        ?>
        <div id="hhb-pricing-rules-admin">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Dates (if seasonal)</th>
                        <th>Modifier</th>
                        <th>Value</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="hhb-rules-body">
                    <?php foreach ( $rules as $rule ) : ?>
                        <tr>
                            <td><?php echo esc_html($rule->rule_type); ?></td>
                            <td><?php echo $rule->start_date ? esc_html($rule->start_date . ' to ' . $rule->end_date) : 'Always'; ?></td>
                            <td><?php echo esc_html($rule->modifier_type); ?></td>
                            <td><?php echo esc_html($rule->value); ?></td>
                            <td>
                                <label><input type="checkbox" name="delete_rules[]" value="<?php echo esc_attr($rule->id); ?>"> 🗑️ Delete</label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="hhb-new-rule-form" style="margin-top:20px; border:1px solid #ccc; padding:15px; background:#f9f9f9;">
                <h4>Add New Rule</h4>
                <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:end;">
                    <div>
                        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px">Type</label>
                        <select name="new_rule_type">
                            <option value="seasonal">Seasonal</option>
                            <option value="weekend">Weekend</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px">Start Date</label>
                        <input type="date" name="new_rule_start">
                    </div>
                    <div>
                        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px">End Date</label>
                        <input type="date" name="new_rule_end">
                    </div>
                    <div>
                        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px">Weekend Days (e.g. 5,6)</label>
                        <input type="text" name="new_rule_days" placeholder="5,6" style="width:80px">
                    </div>
                    <div>
                        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px">Modifier</label>
                        <select name="new_rule_modifier">
                            <option value="fixed">Fixed (+/-)</option>
                            <option value="percentage">Percentage (%)</option>
                            <option value="override">Override Price</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px">Value</label>
                        <input type="number" name="new_rule_value" placeholder="e.g. 500 or 10" step="0.01">
                    </div>
                </div>
                <p class="description" style="margin-top:8px">Rules are saved when you update the post. Weekend days: 1=Mon, 5=Fri, 6=Sat, 7=Sun.</p>
            </div>
        </div>
        <?php
    }

    /**
     * Booking Rules Meta Box — min/max nights, buffer time, deposit.
     */
    public static function render_booking_rules_meta_box( $post ) {
        wp_nonce_field( 'hhb_save_homestay_booking_rules', 'hhb_homestay_booking_rules_nonce' );
        $buffer_days     = get_post_meta( $post->ID, 'hhb_buffer_days', true );
        $deposit_percent = get_post_meta( $post->ID, 'hhb_deposit_percent', true );
        ?>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-top:10px;">
            <div>
                <label style="display:block;font-weight:600;font-size:12px;margin-bottom:3px">Buffer Days (between bookings)</label>
                <input type="number" name="hhb_buffer_days" value="<?php echo esc_attr($buffer_days); ?>" min="0" style="width:100%">
                <p class="description">Days blocked after checkout for cleaning/turnaround across all rooms.</p>
            </div>
            <div>
                <label style="display:block;font-weight:600;font-size:12px;margin-bottom:3px">Deposit % (0 = full payment)</label>
                <input type="number" name="hhb_deposit_percent" value="<?php echo esc_attr($deposit_percent); ?>" min="0" max="100" style="width:100%">
                <p class="description">e.g. 20 means guest pays 20% upfront.</p>
            </div>
        </div>
        <?php
    }

    /**
     * Extra Services Meta Box — CRUD table for add-ons.
     */
    public static function render_extra_services_meta_box( $post ) {
        global $wpdb;
        $table    = $wpdb->prefix . 'himalayan_extra_services';
        $services = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE homestay_id = %d OR homestay_id = 0 ORDER BY sort_order", $post->ID
        ) );
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:20%">Service Name</th>
                    <th style="width:25%">Description</th>
                    <th style="width:12%">Price</th>
                    <th style="width:18%">Price Type</th>
                    <th style="width:8%">Active</th>
                    <th style="width:7%">Scope</th>
                    <th style="width:10%">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $services ) : foreach ( $services as $svc ) : ?>
                    <tr>
                        <td><?php echo esc_html( $svc->service_name ); ?></td>
                        <td><em><?php echo esc_html( $svc->description ); ?></em></td>
                        <td>₹<?php echo esc_html( number_format( $svc->price, 2 ) ); ?></td>
                        <td><?php echo esc_html( ucwords( str_replace('_', ' ', $svc->price_type ) ) ); ?></td>
                        <td><?php echo $svc->is_active ? '✅' : '❌'; ?></td>
                        <td><?php echo $svc->homestay_id == 0 ? '<em>Global</em>' : 'This'; ?></td>
                        <td>
                            <?php if ( $svc->homestay_id != 0 ) : ?>
                                <label><input type="checkbox" name="delete_services[]" value="<?php echo esc_attr($svc->id); ?>"> 🗑️ Delete</label>
                            <?php else : ?>
                                <small>Global (Can't delete here)</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="6" style="text-align:center;color:#999">No extra services yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div style="margin-top:16px; border:1px solid #ccc; padding:15px; background:#f9f9f9; border-radius:4px;">
            <h4 style="margin:0 0 12px">Add New Service</h4>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:end">
                <div style="flex:2">
                    <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px">Name</label>
                    <input type="text" name="new_service_name" placeholder="e.g. Airport Pickup">
                </div>
                <div style="flex:1">
                    <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px">Price</label>
                    <input type="number" name="new_service_price" step="0.01" placeholder="20.00">
                </div>
                <div style="flex:1">
                    <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px">Price Type</label>
                    <select name="new_service_price_type">
                        <option value="flat">Flat (one-time)</option>
                        <option value="per_night">Per Night</option>
                        <option value="per_guest">Per Guest</option>
                        <option value="per_guest_per_night">Per Guest / Night</option>
                    </select>
                </div>
            </div>
            <p class="description" style="margin-top:8px">Service is saved to this homestay when you update the post.</p>
        </div>
        <?php
    }

    /**
     * iCal Feeds Meta Box — manage external sync URLs.
     */
    public static function render_ical_feeds_meta_box( $post ) {
        global $wpdb;
        $table = $wpdb->prefix . 'hhb_ical_feeds';
        
        // Suppress errors briefly in case the table hasn't been created yet on older installs.
        $suppress = $wpdb->suppress_errors();
        $feeds = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE homestay_id = %d", $post->ID ) );
        $wpdb->suppress_errors( $suppress );
        
        // Export URL
        $export_url = site_url( '/wp-json/hhb/v1/ical/' . $post->ID );
        ?>
        <div style="margin-bottom: 20px; padding: 15px; border-left: 4px solid #00a0d2; background: #fff;">
            <p style="margin-top: 0; font-weight: bold;">Export iCal Feed (Copy this to Airbnb/Booking.com)</p>
            <input type="text" readonly="readonly" value="<?php echo esc_url( $export_url ); ?>" style="width: 100%; font-family: monospace; background: #f0f0f1; cursor: text;" onfocus="this.select();">
        </div>

        <p style="font-weight: bold;">Import iCal Feeds (From Airbnb/Booking.com)</p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:20%">Source Name</th>
                    <th style="width:50%">Feed URL (.ics)</th>
                    <th style="width:20%">Last Synced</th>
                    <th style="width:10%">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $feeds ) ) : foreach ( $feeds as $feed ) : ?>
                    <tr>
                        <td><?php echo esc_html( $feed->source_name ); ?></td>
                        <td><a href="<?php echo esc_url( $feed->feed_url ); ?>" target="_blank" style="word-break: break-all;"><?php echo esc_html( $feed->feed_url ); ?></a></td>
                        <td><?php echo $feed->last_synced ? esc_html( $feed->last_synced ) : '<em>Never</em>'; ?></td>
                        <td>
                            <label><input type="checkbox" name="delete_ical_feeds[]" value="<?php echo esc_attr($feed->id); ?>"> 🗑️ Delete</label>
                        </td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="4" style="text-align:center;color:#999">No external iCal feeds connected.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="margin-top:16px; border:1px solid #ccc; padding:15px; background:#f9f9f9; border-radius:4px;">
            <h4 style="margin:0 0 12px">Add New iCal Source</h4>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:end">
                <div style="flex:1">
                    <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px">Source Name</label>
                    <input type="text" name="new_ical_source" placeholder="e.g. Airbnb" style="width: 100%;">
                </div>
                <div style="flex:3">
                    <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px">.ics URL</label>
                    <input type="url" name="new_ical_url" placeholder="https://..." style="width: 100%;">
                </div>
            </div>
            <p class="description" style="margin-top:8px">The feed is saved to this homestay when you update the post.</p>
        </div>
        <?php
    }

    public static function save_meta_boxes( $post_id ) {
        if ( ! isset( $_POST['hhb_homestay_details_nonce'] ) || ! wp_verify_nonce( $_POST['hhb_homestay_details_nonce'], 'hhb_save_homestay_details' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        // Save Gallery
        if ( isset( $_POST['hhb_gallery'] ) ) {
            update_post_meta( $post_id, 'hhb_gallery', sanitize_text_field( $_POST['hhb_gallery'] ) );
        }

        // ── Save Location ─────────────────────────────────────────────────────────
        $text_fields = array(
            'hhb_address', 'hhb_city', 'hhb_state', 'hhb_country', 'hhb_postal_code',
            'hhb_property_type', 'hhb_contact_phone', 'hhb_contact_email',
            'hhb_checkin_time', 'hhb_checkout_time',
            'hhb_early_checkin', 'hhb_late_checkout',
        );
        foreach ( $text_fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $post_id, $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
            }
        }

        // URL field
        if ( isset( $_POST['hhb_website_url'] ) ) {
            update_post_meta( $post_id, 'hhb_website_url', esc_url_raw( wp_unslash( $_POST['hhb_website_url'] ) ) );
        }

        // Numeric fields
        $numeric_fields = array(
            'hhb_total_bedrooms', 'hhb_total_bathrooms', 'hhb_max_guests',
            'hhb_property_size', 'hhb_year_established',
        );
        foreach ( $numeric_fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $post_id, $field, absint( $_POST[ $field ] ) );
            }
        }

        // ── Save House Rules (textarea) ────────────────────────────────────────
        $textarea_fields = array( 'hhb_dos', 'hhb_donts' );
        foreach ( $textarea_fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $post_id, $field, sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) );
            }
        }

        // Save Attractions
        if ( isset( $_POST['hhb_attractions'] ) ) {
            $attractions = array_filter( array_map( 'sanitize_text_field', $_POST['hhb_attractions'] ) );
            update_post_meta( $post_id, 'hhb_attractions', $attractions );
        }

        // Save Amenities (Meta Array instead of Taxonomy)
        if ( isset( $_POST['hhb_amenities'] ) ) {
            $amenities = array_map( 'sanitize_text_field', $_POST['hhb_amenities'] );
            update_post_meta( $post_id, 'hhb_amenities', $amenities );
        } else {
            delete_post_meta( $post_id, 'hhb_amenities' );
        }

        // Save Booking Rules
        $booking_fields = [ 'hhb_buffer_days', 'hhb_deposit_percent' ];
        foreach ( $booking_fields as $f ) {
            if ( isset( $_POST[$f] ) ) {
                update_post_meta( $post_id, $f, sanitize_text_field( $_POST[$f] ) );
            }
        }

        // ── Save Host Profile ──────────────────────────────────────────────────
        if ( isset( $_POST['hhb_host_nonce'] ) && wp_verify_nonce( $_POST['hhb_host_nonce'], 'hhb_save_host_profile' ) ) {
            $host_mode = sanitize_text_field( wp_unslash( $_POST['hhb_host_mode'] ?? 'user' ) );
            $host_mode = in_array( $host_mode, array( 'user', 'manual' ), true ) ? $host_mode : 'user';
            update_post_meta( $post_id, 'hhb_host_mode', $host_mode );

            if ( 'user' === $host_mode ) {
                update_post_meta( $post_id, 'hhb_host_user_id', absint( $_POST['hhb_host_user_id'] ?? 0 ) );
            } else {
                // Manual entry — clear user link, save all manual fields
                delete_post_meta( $post_id, 'hhb_host_user_id' );

                update_post_meta( $post_id, 'hhb_host_name',  sanitize_text_field( wp_unslash( $_POST['hhb_host_name']  ?? '' ) ) );
                update_post_meta( $post_id, 'hhb_host_email', sanitize_email( wp_unslash( $_POST['hhb_host_email'] ?? '' ) ) );
                update_post_meta( $post_id, 'hhb_host_phone', sanitize_text_field( wp_unslash( $_POST['hhb_host_phone'] ?? '' ) ) );
                update_post_meta( $post_id, 'hhb_host_bio',   sanitize_textarea_field( wp_unslash( $_POST['hhb_host_bio'] ?? '' ) ) );

                // Avatar via media library
                $avatar_id = absint( $_POST['hhb_host_avatar_id'] ?? 0 );
                update_post_meta( $post_id, 'hhb_host_avatar_id', $avatar_id );
                if ( $avatar_id ) {
                    $avatar_url = wp_get_attachment_image_url( $avatar_id, 'thumbnail' );
                    update_post_meta( $post_id, 'hhb_host_avatar_url', $avatar_url ?: '' );
                } else {
                    delete_post_meta( $post_id, 'hhb_host_avatar_url' );
                }
            }
        }

        // Delete Services
        if ( !empty($_POST['delete_services']) && is_array($_POST['delete_services']) ) {
            global $wpdb;
            foreach ( $_POST['delete_services'] as $del_id ) {
                $wpdb->delete( $wpdb->prefix . 'himalayan_extra_services', ['id' => intval($del_id), 'homestay_id' => $post_id] );
            }
        }

        // Delete Rules
        if ( !empty($_POST['delete_rules']) && is_array($_POST['delete_rules']) ) {
            global $wpdb;
            foreach ( $_POST['delete_rules'] as $del_id ) {
                $wpdb->delete( $wpdb->prefix . 'himalayan_pricing_rules', ['id' => intval($del_id), 'homestay_id' => $post_id] );
            }
        }

        // Save New Pricing Rule
        if ( !empty($_POST['new_rule_value']) ) {
            global $wpdb;
            $wpdb->insert( $wpdb->prefix . 'himalayan_pricing_rules', array(
                'homestay_id'   => $post_id,
                'rule_type'     => sanitize_text_field($_POST['new_rule_type']),
                'start_date'    => !empty($_POST['new_rule_start']) ? sanitize_text_field($_POST['new_rule_start']) : null,
                'end_date'      => !empty($_POST['new_rule_end']) ? sanitize_text_field($_POST['new_rule_end']) : null,
                'days_of_week'  => !empty($_POST['new_rule_days']) ? sanitize_text_field($_POST['new_rule_days']) : '',
                'modifier_type' => sanitize_text_field($_POST['new_rule_modifier']),
                'value'         => floatval($_POST['new_rule_value']),
            ));
        }

        // Save New Extra Service
        if ( !empty($_POST['new_service_name']) && !empty($_POST['new_service_price']) ) {
            global $wpdb;
            $wpdb->insert( $wpdb->prefix . 'himalayan_extra_services', array(
                'homestay_id'  => $post_id,
                'service_name' => sanitize_text_field($_POST['new_service_name']),
                'price'        => floatval($_POST['new_service_price']),
                'price_type'   => sanitize_text_field($_POST['new_service_price_type']),
                'is_active'    => 1,
            ));
        }

        // Save New iCal Feed
        if ( !empty($_POST['new_ical_source']) && !empty($_POST['new_ical_url']) ) {
            global $wpdb;
            $wpdb->insert( $wpdb->prefix . 'hhb_ical_feeds', array(
                'homestay_id' => $post_id,
                'source_name' => sanitize_text_field( $_POST['new_ical_source'] ),
                'feed_url'    => esc_url_raw( $_POST['new_ical_url'] ),
                'is_active'   => 1,
            ));
        }

        // Delete iCal Feeds
        if ( !empty($_POST['delete_ical_feeds']) && is_array($_POST['delete_ical_feeds']) ) {
            global $wpdb;
            foreach ( $_POST['delete_ical_feeds'] as $del_id ) {
                $wpdb->delete( $wpdb->prefix . 'hhb_ical_feeds', ['id' => intval($del_id), 'homestay_id' => $post_id] );
            }
        }

        // ── Save Rooms Repeater ──────────────────────────────────────────────────
        if ( isset( $_POST['hhb_rooms_nonce'] ) && wp_verify_nonce( $_POST['hhb_rooms_nonce'], 'hhb_save_rooms' ) ) {
            $rooms_input = isset( $_POST['hhb_rooms'] ) && is_array( $_POST['hhb_rooms'] ) ? $_POST['hhb_rooms'] : array();
            $base_prices = array();

            foreach ( $rooms_input as $room_data ) {
                $room_id   = isset( $room_data['id'] ) ? intval( $room_data['id'] ) : 0;
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

                if ( $room_id > 0 ) {
                    // Update existing room title. RoomMetaBoxes is not initialized so no recursion risk.
                    wp_update_post( array( 'ID' => $room_id, 'post_title' => $title, 'post_name' => '' ) );
                } else {
                    // Create new child room
                    $room_id = wp_insert_post( array(
                        'post_title'  => $title,
                        'post_type'   => 'hhb_room',
                        'post_status' => 'publish',
                        'post_parent' => $post_id,
                    ) );
                    if ( is_wp_error( $room_id ) || ! $room_id ) {
                        continue;
                    }
                    // Ensure parent is set via meta as fallback
                    update_post_meta( $room_id, '_hhb_homestay_id', $post_id );
                }

                // Save all room meta
                $base_price = floatval( $room_data['base_price'] ?? 0 );
                update_post_meta( $room_id, 'room_base_price',      $base_price );
                update_post_meta( $room_id, 'room_weekend_price',   floatval( $room_data['weekend_price'] ?? 0 ) );
                update_post_meta( $room_id, 'room_extra_guest_fee', floatval( $room_data['extra_guest_fee'] ?? 0 ) );
                update_post_meta( $room_id, 'room_max_guests',      max( 1, intval( $room_data['max_guests'] ?? 2 ) ) );
                update_post_meta( $room_id, 'room_quantity',        max( 1, intval( $room_data['quantity'] ?? 1 ) ) );
                update_post_meta( $room_id, 'room_min_nights',      max( 1, intval( $room_data['min_nights'] ?? 1 ) ) );
                update_post_meta( $room_id, 'room_max_nights',      max( 1, intval( $room_data['max_nights'] ?? 30 ) ) );
                update_post_meta( $room_id, 'room_size_sqft',       sanitize_text_field( $room_data['size_sqft'] ?? '' ) );
                update_post_meta( $room_id, 'room_bed_type',        sanitize_text_field( $room_data['bed_type'] ?? '' ) );

                if ( $base_price > 0 ) {
                    $base_prices[] = $base_price;
                }
            }

            // Recalculate and cache price range on the homestay
            if ( ! empty( $base_prices ) ) {
                update_post_meta( $post_id, 'hhb_price_min', min( $base_prices ) );
                update_post_meta( $post_id, 'hhb_price_max', max( $base_prices ) );
            } else {
                delete_post_meta( $post_id, 'hhb_price_min' );
                delete_post_meta( $post_id, 'hhb_price_max' );
            }
        }

    }

    public static function render_host_meta_box( $post ) {
        wp_nonce_field( 'hhb_save_host_profile', 'hhb_host_nonce' );

        $host_mode      = get_post_meta( $post->ID, 'hhb_host_mode', true ) ?: 'user';
        $host_user_id   = get_post_meta( $post->ID, 'hhb_host_user_id', true );
        $host_name      = get_post_meta( $post->ID, 'hhb_host_name', true );
        $host_email     = get_post_meta( $post->ID, 'hhb_host_email', true );
        $host_phone     = get_post_meta( $post->ID, 'hhb_host_phone', true );
        $host_bio       = get_post_meta( $post->ID, 'hhb_host_bio', true );
        $host_avatar_id = (int) get_post_meta( $post->ID, 'hhb_host_avatar_id', true );
        $host_avatar_url = $host_avatar_id
            ? wp_get_attachment_image_url( $host_avatar_id, 'thumbnail' )
            : get_post_meta( $post->ID, 'hhb_host_avatar_url', true );

        // WP User data for preview
        $wp_user_preview = null;
        if ( $host_user_id ) {
            $u = get_userdata( $host_user_id );
            if ( $u ) {
                $wp_user_preview = array(
                    'name'   => $u->display_name,
                    'email'  => $u->user_email,
                    'avatar' => get_avatar_url( $u->ID, array( 'size' => 48 ) ),
                );
            }
        }

        $users = get_users( array(
            'orderby' => 'display_name',
            'fields'  => array( 'ID', 'display_name', 'user_email' ),
        ) );
        ?>
        <style>
            /* ── Host Metabox ──────────────────────────────────────── */
            #hhb-host-mode-tabs { display:flex; gap:0; margin-bottom:14px; border:1px solid #c3c4c7; border-radius:5px; overflow:hidden; }
            #hhb-host-mode-tabs button {
                flex:1; padding:8px 4px; font-size:11px; font-weight:700; background:#f6f7f7;
                border:none; border-right:1px solid #c3c4c7; cursor:pointer; text-transform:uppercase;
                letter-spacing:.4px; color:#50575e; transition:background .15s,color .15s;
            }
            #hhb-host-mode-tabs button:last-child { border-right:none; }
            #hhb-host-mode-tabs button.hhb-tab-active { background:#2271b1; color:#fff; }
            .hhb-host-panel { display:none; }
            .hhb-host-panel.hhb-panel-active { display:block; }
            .hhb-hf { margin-bottom:10px; }
            .hhb-hf label { display:block; font-size:11px; font-weight:700; color:#50575e; margin-bottom:3px; text-transform:uppercase; letter-spacing:.3px; }
            .hhb-hf input[type="text"],
            .hhb-hf input[type="email"],
            .hhb-hf input[type="tel"],
            .hhb-hf select,
            .hhb-hf textarea { width:100%; }
            .hhb-hf textarea { resize:vertical; min-height:70px; }
            /* User preview card */
            #hhb-user-preview {
                display:flex; align-items:center; gap:10px; padding:10px 12px;
                background:#f0f6fc; border:1px solid #c3c4c7; border-radius:5px; margin-top:10px;
            }
            #hhb-user-preview img { width:44px; height:44px; border-radius:50%; object-fit:cover; flex-shrink:0; border:2px solid #fff; box-shadow:0 1px 4px rgba(0,0,0,.15); }
            #hhb-user-preview .hhb-up-info strong { display:block; font-size:13px; font-weight:700; color:#1d2327; }
            #hhb-user-preview .hhb-up-info span { font-size:11px; color:#646970; }
            /* Avatar uploader */
            #hhb-avatar-wrap { display:flex; align-items:center; gap:10px; margin-top:4px; }
            #hhb-avatar-preview { width:56px; height:56px; border-radius:50%; object-fit:cover; border:2px solid #c3c4c7; display:<?php echo $host_avatar_url ? 'block' : 'none'; ?>; }
            #hhb-avatar-remove { display:<?php echo $host_avatar_url ? 'inline-block' : 'none'; ?>; }
        </style>

        <!-- Mode toggle -->
        <input type="hidden" name="hhb_host_mode" id="hhb_host_mode" value="<?php echo esc_attr( $host_mode ); ?>">
        <div id="hhb-host-mode-tabs">
            <button type="button" class="<?php echo $host_mode === 'user'   ? 'hhb-tab-active' : ''; ?>" data-mode="user">WP User</button>
            <button type="button" class="<?php echo $host_mode === 'manual' ? 'hhb-tab-active' : ''; ?>" data-mode="manual">Manual Entry</button>
        </div>

        <!-- ── Panel: WP User ──────────────────────────────────── -->
        <div class="hhb-host-panel <?php echo $host_mode === 'user' ? 'hhb-panel-active' : ''; ?>" id="hhb-panel-user">
            <div class="hhb-hf">
                <label for="hhb_host_user_id">Select WordPress User</label>
                <select name="hhb_host_user_id" id="hhb_host_user_id">
                    <option value="">— No user selected —</option>
                    <?php foreach ( $users as $u ) : ?>
                        <option value="<?php echo esc_attr( $u->ID ); ?>"
                            data-name="<?php echo esc_attr( $u->display_name ); ?>"
                            data-email="<?php echo esc_attr( $u->user_email ); ?>"
                            data-avatar="<?php echo esc_url( get_avatar_url( $u->ID, array( 'size' => 48 ) ) ); ?>"
                            <?php selected( $host_user_id, $u->ID ); ?>>
                            <?php echo esc_html( $u->display_name . ' — ' . $u->user_email ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description" style="margin-top:5px; font-size:11px;">User's name, email &amp; avatar will display on the frontend.</p>
            </div>

            <!-- Live preview card -->
            <div id="hhb-user-preview" style="<?php echo $wp_user_preview ? '' : 'display:none;'; ?>">
                <img id="hhb-up-avatar" src="<?php echo $wp_user_preview ? esc_url( $wp_user_preview['avatar'] ) : ''; ?>" alt="">
                <div class="hhb-up-info">
                    <strong id="hhb-up-name"><?php echo $wp_user_preview ? esc_html( $wp_user_preview['name'] ) : ''; ?></strong>
                    <span id="hhb-up-email"><?php echo $wp_user_preview ? esc_html( $wp_user_preview['email'] ) : ''; ?></span>
                </div>
            </div>
        </div>

        <!-- ── Panel: Manual Entry ─────────────────────────────── -->
        <div class="hhb-host-panel <?php echo $host_mode === 'manual' ? 'hhb-panel-active' : ''; ?>" id="hhb-panel-manual">

            <div class="hhb-hf">
                <label>Avatar Photo</label>
                <div id="hhb-avatar-wrap">
                    <img id="hhb-avatar-preview" src="<?php echo esc_url( $host_avatar_url ); ?>" alt="Host Avatar">
                    <div>
                        <button type="button" class="button" id="hhb-avatar-select">Select Photo</button>
                        <button type="button" class="button-link" id="hhb-avatar-remove" style="color:#b32d2e; margin-left:6px; font-size:11px;">Remove</button>
                    </div>
                </div>
                <input type="hidden" name="hhb_host_avatar_id" id="hhb_host_avatar_id" value="<?php echo esc_attr( $host_avatar_id ); ?>">
            </div>

            <div class="hhb-hf">
                <label for="hhb_host_name">Full Name *</label>
                <input type="text" name="hhb_host_name" id="hhb_host_name" value="<?php echo esc_attr( $host_name ); ?>" placeholder="e.g. Ramesh Thapa">
            </div>

            <div class="hhb-hf">
                <label for="hhb_host_email">Email Address</label>
                <input type="email" name="hhb_host_email" id="hhb_host_email" value="<?php echo esc_attr( $host_email ); ?>" placeholder="host@example.com">
            </div>

            <div class="hhb-hf">
                <label for="hhb_host_phone">Phone / WhatsApp</label>
                <input type="tel" name="hhb_host_phone" id="hhb_host_phone" value="<?php echo esc_attr( $host_phone ); ?>" placeholder="+91 98765 43210">
            </div>

            <div class="hhb-hf">
                <label for="hhb_host_bio">About the Host</label>
                <textarea name="hhb_host_bio" id="hhb_host_bio" rows="4" placeholder="Brief introduction shown to guests on the property page..."><?php echo esc_textarea( $host_bio ); ?></textarea>
            </div>

            <?php if ( $host_name || $host_email ) : ?>
            <div style="display:flex; align-items:center; gap:10px; padding:10px; background:#f0f6fc; border:1px solid #c3c4c7; border-radius:5px; margin-top:4px;">
                <?php if ( $host_avatar_url ) : ?>
                    <img src="<?php echo esc_url( $host_avatar_url ); ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;" alt="">
                <?php endif; ?>
                <div style="font-size:12px;">
                    <strong style="display:block;"><?php echo esc_html( $host_name ); ?></strong>
                    <span style="color:#646970;"><?php echo esc_html( $host_email ); ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <script>
        (function($) {
            // ── Tab switching ──────────────────────────────────────
            $('#hhb-host-mode-tabs button').on('click', function() {
                var mode = $(this).data('mode');
                $('#hhb_host_mode').val(mode);
                $('#hhb-host-mode-tabs button').removeClass('hhb-tab-active');
                $(this).addClass('hhb-tab-active');
                $('.hhb-host-panel').removeClass('hhb-panel-active');
                $('#hhb-panel-' + mode).addClass('hhb-panel-active');
            });

            // ── WP User select → live preview ─────────────────────
            $('#hhb_host_user_id').on('change', function() {
                var $opt = $(this).find(':selected');
                var uid  = $(this).val();
                if ( uid ) {
                    $('#hhb-up-name').text( $opt.data('name') );
                    $('#hhb-up-email').text( $opt.data('email') );
                    $('#hhb-up-avatar').attr( 'src', $opt.data('avatar') );
                    $('#hhb-user-preview').show();
                } else {
                    $('#hhb-user-preview').hide();
                }
            });

            // ── Avatar uploader ────────────────────────────────────
            var hhbAvatarFrame;
            $('#hhb-avatar-select').on('click', function(e) {
                e.preventDefault();
                if ( hhbAvatarFrame ) { hhbAvatarFrame.open(); return; }
                hhbAvatarFrame = wp.media({
                    title:    'Select Host Avatar',
                    button:   { text: 'Use this photo' },
                    library:  { type: 'image' },
                    multiple: false,
                });
                hhbAvatarFrame.on('select', function() {
                    var attachment = hhbAvatarFrame.state().get('selection').first().toJSON();
                    var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                    $('#hhb_host_avatar_id').val( attachment.id );
                    $('#hhb-avatar-preview').attr('src', url).show();
                    $('#hhb-avatar-remove').show();
                });
                hhbAvatarFrame.open();
            });

            $('#hhb-avatar-remove').on('click', function() {
                $('#hhb_host_avatar_id').val('');
                $('#hhb-avatar-preview').attr('src', '').hide();
                $(this).hide();
            });
        })(jQuery);
        </script>
        <?php
    }
}


