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
        add_meta_box( 'hhb_homestay_details', __( 'Homestay Details & Pricing', 'himalayan-homestay-bookings' ), array( __CLASS__, 'render_details_meta_box' ), 'hhb_homestay', 'normal', 'high' );
        add_meta_box( 'hhb_homestay_amenities', __( 'Property Amenities', 'himalayan-homestay-bookings' ), array( __CLASS__, 'render_amenities_meta_box' ), 'hhb_homestay', 'normal', 'high' );
        add_meta_box( 'hhb_homestay_rules', __( 'House Rules (Dos & Don\'ts)', 'himalayan-homestay-bookings' ), array( __CLASS__, 'render_rules_meta_box' ), 'hhb_homestay', 'normal', 'default' );
        add_meta_box( 'hhb_homestay_attractions', __( 'Nearby Attractions', 'himalayan-homestay-bookings' ), array( __CLASS__, 'render_attractions_meta_box' ), 'hhb_homestay', 'normal', 'default' );
        add_meta_box( 'hhb_homestay_pricing_rules', __( 'Advanced Pricing Rules (Seasonal/Weekend)', 'himalayan-homestay-bookings' ), array( __CLASS__, 'render_pricing_rules_meta_box' ), 'hhb_homestay', 'normal', 'default' );
        add_meta_box( 'hhb_homestay_booking_rules', __( 'Booking Rules', 'himalayan-homestay-bookings' ), array( __CLASS__, 'render_booking_rules_meta_box' ), 'hhb_homestay', 'side', 'default' );
        add_meta_box( 'hhb_homestay_extra_services', __( 'Extra Services & Add-ons', 'himalayan-homestay-bookings' ), array( __CLASS__, 'render_extra_services_meta_box' ), 'hhb_homestay', 'normal', 'default' );
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
        $base_price    = get_post_meta( $post->ID, 'base_price_per_night', true );
        $offer_price   = get_post_meta( $post->ID, 'offer_price_per_night', true );
        $currency      = get_post_meta( $post->ID, 'currency', true ) ?: 'INR';
        $max_guests    = get_post_meta( $post->ID, 'max_guests', true );
        $owner_id      = get_post_meta( $post->ID, 'owner_id', true );
        $lat           = get_post_meta( $post->ID, 'lat', true );
        $lng           = get_post_meta( $post->ID, 'lng', true );
        ?>
        <div class="hhb-meta-container">
            <div class="hhb-field">
                <label>Base Price / Night</label>
                <input type="number" name="base_price_per_night" value="<?php echo esc_attr($base_price); ?>" step="0.01">
            </div>
            <div class="hhb-field">
                <label>Offer Price / Night (Discounted)</label>
                <input type="number" name="offer_price_per_night" value="<?php echo esc_attr($offer_price); ?>" step="0.01">
                <p class="description">Set a lower price to show a discount badge.</p>
            </div>
            <div class="hhb-field">
                <label>Max Guests</label>
                <input type="number" name="max_guests" value="<?php echo esc_attr($max_guests); ?>">
            </div>
            <div class="hhb-field">
                <label>GPS Coordinates</label>
                <div style="display:flex; gap:10px;">
                    <input type="text" name="lat" placeholder="Latitude" value="<?php echo esc_attr($lat); ?>">
                    <input type="text" name="lng" placeholder="Longitude" value="<?php echo esc_attr($lng); ?>">
                </div>
            </div>
        </div>
        <style>
            .hhb-meta-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
            .hhb-field label { display: block; font-weight: bold; margin-bottom: 5px; }
            .hhb-field input { width: 100%; }
        </style>
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
                                <!-- Real delete functionality would be built into a REST API, providing a link for now -->
                                <button type="button" class="button delete-rule" disabled title="Will be hooked to AJAX">Delete</button>
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
        $min_nights       = get_post_meta( $post->ID, 'hhb_min_nights', true ) ?: 1;
        $max_nights       = get_post_meta( $post->ID, 'hhb_max_nights', true ) ?: 30;
        $buffer_days      = get_post_meta( $post->ID, 'hhb_buffer_days', true ) ?: 0;
        $deposit_percent  = get_post_meta( $post->ID, 'hhb_deposit_percent', true ) ?: 0;
        $extra_guest_fee  = get_post_meta( $post->ID, 'hhb_extra_guest_fee', true ) ?: 0;
        ?>
        <div style="display:flex; flex-direction:column; gap:12px;">
            <div>
                <label style="display:block;font-weight:600;font-size:12px;margin-bottom:3px">Minimum Nights</label>
                <input type="number" name="hhb_min_nights" value="<?php echo esc_attr($min_nights); ?>" min="1" style="width:100%">
            </div>
            <div>
                <label style="display:block;font-weight:600;font-size:12px;margin-bottom:3px">Maximum Nights</label>
                <input type="number" name="hhb_max_nights" value="<?php echo esc_attr($max_nights); ?>" min="1" style="width:100%">
            </div>
            <div>
                <label style="display:block;font-weight:600;font-size:12px;margin-bottom:3px">Buffer Days (between bookings)</label>
                <input type="number" name="hhb_buffer_days" value="<?php echo esc_attr($buffer_days); ?>" min="0" style="width:100%">
                <p class="description">Days blocked after checkout for cleaning/turnaround.</p>
            </div>
            <div>
                <label style="display:block;font-weight:600;font-size:12px;margin-bottom:3px">Extra Guest Fee (per night)</label>
                <input type="number" name="hhb_extra_guest_fee" value="<?php echo esc_attr($extra_guest_fee); ?>" step="0.01" min="0" style="width:100%">
                <p class="description">Charged per guest beyond the max included.</p>
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
                    <th style="width:25%">Service Name</th>
                    <th style="width:30%">Description</th>
                    <th style="width:12%">Price</th>
                    <th style="width:18%">Price Type</th>
                    <th style="width:8%">Active</th>
                    <th style="width:7%">Scope</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $services ) : foreach ( $services as $svc ) : ?>
                    <tr>
                        <td><?php echo esc_html( $svc->service_name ); ?></td>
                        <td><em><?php echo esc_html( $svc->description ); ?></em></td>
                        <td>$<?php echo esc_html( number_format( $svc->price, 2 ) ); ?></td>
                        <td><?php echo esc_html( ucwords( str_replace('_', ' ', $svc->price_type ) ) ); ?></td>
                        <td><?php echo $svc->is_active ? '✅' : '❌'; ?></td>
                        <td><?php echo $svc->homestay_id == 0 ? '<em>Global</em>' : 'This'; ?></td>
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

    public static function save_meta_boxes( $post_id ) {
        if ( ! isset( $_POST['hhb_homestay_details_nonce'] ) || ! wp_verify_nonce( $_POST['hhb_homestay_details_nonce'], 'hhb_save_homestay_details' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        // Save Gallery
        if ( isset( $_POST['hhb_gallery'] ) ) {
            update_post_meta( $post_id, 'hhb_gallery', sanitize_text_field( $_POST['hhb_gallery'] ) );
        }

        // Save Basic Meta
        $meta_fields = array(
            'base_price_per_night', 'offer_price_per_night', 'max_guests', 'lat', 'lng', 
            'hhb_dos', 'hhb_donts'
        );
        foreach ( $meta_fields as $field ) {
            if ( isset( $_POST[$field] ) ) update_post_meta( $post_id, $field, sanitize_textarea_field($_POST[$field]) );
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
        $booking_fields = [ 'hhb_min_nights', 'hhb_max_nights', 'hhb_buffer_days', 'hhb_deposit_percent', 'hhb_extra_guest_fee' ];
        foreach ( $booking_fields as $f ) {
            if ( isset( $_POST[$f] ) ) {
                update_post_meta( $post_id, $f, sanitize_text_field( $_POST[$f] ) );
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
    }
}


