<?php
namespace Himalayan\Homestay\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PostTypes {
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_post_types' ) );
    }

    public static function register_post_types() {
        $labels = array(
            'name'                  => _x( 'Homestays', 'Post Type General Name', 'himalayan-homestay-bookings' ),
            'singular_name'         => _x( 'Homestay', 'Post Type Singular Name', 'himalayan-homestay-bookings' ),
            'menu_name'             => __( 'Homestays', 'himalayan-homestay-bookings' ),
            'all_items'             => __( 'All Homestays', 'himalayan-homestay-bookings' ),
            'add_new_item'          => __( 'Add New Homestay', 'himalayan-homestay-bookings' ),
            'add_new'               => __( 'Add New', 'himalayan-homestay-bookings' ),
            'edit_item'             => __( 'Edit Homestay', 'himalayan-homestay-bookings' ),
        );
        $args = array(
            'label'                 => __( 'Homestay', 'himalayan-homestay-bookings' ),
            'labels'                => $labels,
            'supports'              => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-building',
            'has_archive'           => true,
            'rewrite'               => array( 'slug' => 'homestays', 'with_front' => false, 'pages' => true, 'feeds' => true ),
            'show_in_rest'          => true,
        );
        register_post_type( 'hhb_homestay', $args );

        // Register Host Applications CPT
        $host_labels = array(
            'name'                  => _x( 'Host Applications', 'Post Type General Name', 'himalayan-homestay-bookings' ),
            'singular_name'         => _x( 'Host Application', 'Post Type Singular Name', 'himalayan-homestay-bookings' ),
            'menu_name'             => __( 'Host Apps', 'himalayan-homestay-bookings' ),
            'all_items'             => __( 'Host Applications', 'himalayan-homestay-bookings' ),
        );
        $host_args = array(
            'label'                 => __( 'Host Application', 'himalayan-homestay-bookings' ),
            'labels'                => $host_labels,
            'supports'              => false, // Hide editor and title completely
            'public'                => false,
            'show_ui'               => false, // Hide default WP post list UI
            'show_in_menu'          => false,
            'has_archive'           => false,
            'show_in_rest'          => false,
            'capability_type'       => 'post',
        );
        register_post_type( 'hhb_host_app', $host_args );
    }
}


