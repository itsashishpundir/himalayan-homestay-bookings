<?php
namespace Himalayan\Homestay\Interface\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class TaxonomyMeta {

    public static function init() {
        $taxonomies = array('hhb_location', 'hhb_property_type');
        
        foreach ($taxonomies as $tax) {
            add_action( "{$tax}_add_form_fields", array( __CLASS__, 'add_meta_fields' ), 10, 2 );
            add_action( "{$tax}_edit_form_fields", array( __CLASS__, 'edit_meta_fields' ), 10, 2 );
            add_action( "created_{$tax}", array( __CLASS__, 'save_meta_fields' ), 10, 2 );
            add_action( "edited_{$tax}", array( __CLASS__, 'save_meta_fields' ), 10, 2 );
        }

        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_media'));
    }

    public static function enqueue_media() {
        if (isset($_GET['taxonomy']) && in_array($_GET['taxonomy'], array('hhb_location', 'hhb_property_type'))) {
            wp_enqueue_media();
        }
    }

    public static function add_meta_fields() {
        ?>
        <div class="form-field">
            <label for="hhb_term_title">Custom Title (Optional)</label>
            <input type="text" name="hhb_term_title" id="hhb_term_title" value="">
            <p class="description">Used in the hero section. Defaults to term name if empty.</p>
        </div>
        <div class="form-field">
            <label for="hhb_term_image">Hero Background Image</label>
            <input type="hidden" name="hhb_term_image" id="hhb_term_image" value="">
            <div id="hhb_term_image_wrapper"></div>
            <p><button type="button" class="button button-secondary hhb-upload-image">Upload/Add Image</button> <button type="button" class="button button-secondary hhb-remove-image" style="display:none;">Remove Image</button></p>
        </div>
        <div class="form-field">
            <label for="hhb_term_bottom_content">Bottom Content</label>
            <?php wp_editor('', 'hhb_term_bottom_content', array('textarea_name' => 'hhb_term_bottom_content', 'textarea_rows' => 10)); ?>
            <p class="description">Content to display at the bottom of the category page (good for SEO like blogs).</p>
        </div>
        <?php self::render_js(); ?>
        <?php
    }

    public static function edit_meta_fields( $term ) {
        $term_id = $term->term_id;
        $title = get_term_meta( $term_id, 'hhb_term_title', true );
        $image_id = get_term_meta( $term_id, 'hhb_term_image', true );
        $bottom_content = get_term_meta( $term_id, 'hhb_term_bottom_content', true );
        
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
        ?>
        <tr class="form-field">
            <th scope="row"><label for="hhb_term_title">Custom Title</label></th>
            <td>
                <input type="text" name="hhb_term_title" id="hhb_term_title" value="<?php echo esc_attr($title); ?>">
                <p class="description">Used in the hero section. Defaults to term name if empty.</p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="hhb_term_image">Hero Background Image</label></th>
            <td>
                <input type="hidden" name="hhb_term_image" id="hhb_term_image" value="<?php echo esc_attr($image_id); ?>">
                <div id="hhb_term_image_wrapper">
                    <?php if ($image_url) : ?>
                        <img src="<?php echo esc_url($image_url); ?>" style="max-width:150px; height:auto; display:block; margin-bottom:10px;">
                    <?php endif; ?>
                </div>
                <p>
                    <button type="button" class="button button-secondary hhb-upload-image">Upload/Add Image</button> 
                    <button type="button" class="button button-secondary hhb-remove-image" style="<?php echo $image_id ? '' : 'display:none;'; ?>">Remove Image</button>
                </p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="hhb_term_bottom_content">Bottom Content</label></th>
            <td>
                <?php wp_editor($bottom_content, 'hhb_term_bottom_content', array('textarea_name' => 'hhb_term_bottom_content', 'textarea_rows' => 10)); ?>
                <p class="description">Content to display at the bottom of the category page (good for SEO like blogs).</p>
            </td>
        </tr>
        <?php self::render_js(); ?>
        <?php
    }

    private static function render_js() {
        ?>
        <script>
            jQuery(document).ready(function($){
                var file_frame;
                $('.hhb-upload-image').on('click', function(e){
                    e.preventDefault();
                    if ( file_frame ) { file_frame.open(); return; }
                    file_frame = wp.media.frames.file_frame = wp.media({
                        title: 'Select a image to upload',
                        button: { text: 'Use this image' },
                        multiple: false
                    });
                    file_frame.on( 'select', function() {
                        var attachment = file_frame.state().get('selection').first().toJSON();
                        $('#hhb_term_image').val(attachment.id);
                        $('#hhb_term_image_wrapper').html('<img src="'+attachment.url+'" style="max-width:150px; height:auto; display:block; margin-bottom:10px;">');
                        $('.hhb-remove-image').show();
                    });
                    file_frame.open();
                });
                $('.hhb-remove-image').on('click', function(e){
                    e.preventDefault();
                    $('#hhb_term_image').val('');
                    $('#hhb_term_image_wrapper').html('');
                    $(this).hide();
                });
            });
        </script>
        <?php
    }

    public static function save_meta_fields( $term_id ) {
        if ( isset( $_POST['hhb_term_title'] ) ) {
            update_term_meta( $term_id, 'hhb_term_title', sanitize_text_field( $_POST['hhb_term_title'] ) );
        }
        if ( isset( $_POST['hhb_term_image'] ) ) {
            update_term_meta( $term_id, 'hhb_term_image', sanitize_text_field( $_POST['hhb_term_image'] ) );
        }
        if ( isset( $_POST['hhb_term_bottom_content'] ) ) {
            // allow safe html
            update_term_meta( $term_id, 'hhb_term_bottom_content', wp_kses_post( $_POST['hhb_term_bottom_content'] ) );
        }
    }
}