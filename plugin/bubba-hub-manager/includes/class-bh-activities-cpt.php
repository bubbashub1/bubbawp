<?php
/**
 * Activities custom post type.
 * Mirrors the CPT UI definition exactly so the switch-over changes nothing.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Activities_CPT {

    public static function register() {
        add_action( 'init', array( __CLASS__, 'register_post_type' ) );
    }

    public static function register_post_type() {
        $labels = array(
            'name'          => esc_html__( 'Activities', 'bubba-hub' ),
            'singular_name' => esc_html__( 'Activity', 'bubba-hub' ),
        );

        $args = array(
            'label'                 => esc_html__( 'Activities', 'bubba-hub' ),
            'labels'                => $labels,
            'description'           => '',
            'public'                => true,
            'publicly_queryable'    => true,
            'show_ui'               => true,
            'show_in_rest'          => true,
            'rest_base'             => '',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'rest_namespace'        => 'wp/v2',
            'has_archive'           => false,
            'show_in_menu'          => true,
            'show_in_nav_menus'     => true,
            'delete_with_user'      => false,
            'exclude_from_search'   => false,
            'capability_type'       => 'post',
            'map_meta_cap'          => true,
            'hierarchical'          => false,
            'can_export'            => false,
            'rewrite'               => array( 'slug' => 'activities', 'with_front' => true ),
            'query_var'             => true,
            'supports'              => array( 'title', 'editor', 'thumbnail' ),
            'show_in_graphql'       => false,
        );

        register_post_type( 'activities', $args );
    }
}
