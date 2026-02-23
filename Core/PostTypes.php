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
    }
}


