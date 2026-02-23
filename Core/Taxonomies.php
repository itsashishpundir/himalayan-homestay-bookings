<?php
namespace Himalayan\Homestay\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Taxonomies {
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_taxonomies' ) );
    }

    public static function register_taxonomies() {
        // Register 'Location' taxonomy (Country > State > City > Area)
        $location_labels = array(
            'name'              => _x( 'Locations', 'taxonomy general name', 'himalayan-homestay-bookings' ),
            'singular_name'     => _x( 'Location', 'taxonomy singular name', 'himalayan-homestay-bookings' ),
            'search_items'      => __( 'Search Locations', 'himalayan-homestay-bookings' ),
            'all_items'         => __( 'All Locations', 'himalayan-homestay-bookings' ),
            'parent_item'       => __( 'Parent Location', 'himalayan-homestay-bookings' ),
            'parent_item_colon' => __( 'Parent Location:', 'himalayan-homestay-bookings' ),
            'edit_item'         => __( 'Edit Location', 'himalayan-homestay-bookings' ),
            'update_item'       => __( 'Update Location', 'himalayan-homestay-bookings' ),
            'add_new_item'      => __( 'Add New Location', 'himalayan-homestay-bookings' ),
            'new_item_name'     => __( 'New Location Name', 'himalayan-homestay-bookings' ),
            'menu_name'         => __( 'Locations', 'himalayan-homestay-bookings' ),
        );
        $location_args = array(
            'hierarchical'      => true,
            'labels'            => $location_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'location', 'with_front' => false ),
            'show_in_rest'      => true,
        );
        register_taxonomy( 'hhb_location', array( 'hhb_homestay' ), $location_args );

        // Register 'Property Type' taxonomy (acts like tags)
        $type_labels = array(
            'name'                       => _x( 'Property Types', 'taxonomy general name', 'himalayan-homestay-bookings' ),
            'singular_name'              => _x( 'Property Type', 'taxonomy singular name', 'himalayan-homestay-bookings' ),
            'search_items'               => __( 'Search Property Types', 'himalayan-homestay-bookings' ),
            'popular_items'              => __( 'Popular Property Types', 'himalayan-homestay-bookings' ),
            'all_items'                  => __( 'All Property Types', 'himalayan-homestay-bookings' ),
            'edit_item'                  => __( 'Edit Property Type', 'himalayan-homestay-bookings' ),
            'update_item'                => __( 'Update Property Type', 'himalayan-homestay-bookings' ),
            'add_new_item'               => __( 'Add New Property Type', 'himalayan-homestay-bookings' ),
            'new_item_name'              => __( 'New Property Type Name', 'himalayan-homestay-bookings' ),
            'separate_items_with_commas' => __( 'Separate property types with commas', 'himalayan-homestay-bookings' ),
            'add_or_remove_items'        => __( 'Add or remove property types', 'himalayan-homestay-bookings' ),
            'choose_from_most_used'      => __( 'Choose from the most used property types', 'himalayan-homestay-bookings' ),
            'not_found'                  => __( 'No property types found.', 'himalayan-homestay-bookings' ),
            'menu_name'                  => __( 'Property Types', 'himalayan-homestay-bookings' ),
        );

        $type_args = array(
            'hierarchical'          => false, // False acts like tags
            'labels'                => $type_labels,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'update_count_callback' => '_update_post_term_count',
            'query_var'             => true,
            'rewrite'               => array( 'slug' => 'property-type', 'with_front' => false ),
            'show_in_rest'          => true,
        );
        register_taxonomy( 'hhb_property_type', array( 'hhb_homestay' ), $type_args );
    }
}


