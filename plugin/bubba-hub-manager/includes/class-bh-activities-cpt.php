<?php
/**
 * Activities custom post type and its taxonomies.
 * Mirrors the CPT UI definitions exactly so the switch-over changes nothing.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Activities_CPT {

    public static function register() {
        add_action( 'init', array( __CLASS__, 'register_post_type' ) );
        add_action( 'init', array( __CLASS__, 'register_taxonomies' ) );
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

    public static function register_taxonomies() {
        self::register_activity_taxonomy(
            'activity_category',
            __( 'Activity Categories', 'bubba-hub' ),
            __( 'Activity Category', 'bubba-hub' )
        );
        self::register_activity_taxonomy(
            'activity_region',
            __( 'Regions', 'bubba-hub' ),
            __( 'Region', 'bubba-hub' )
        );
    }

    private static function register_activity_taxonomy( $slug, $plural, $singular ) {
        $labels = array(
            'name'          => esc_html( $plural ),
            'singular_name' => esc_html( $singular ),
        );

        $args = array(
            'label'                 => esc_html( $plural ),
            'labels'                => $labels,
            'public'                => true,
            'publicly_queryable'    => true,
            'hierarchical'          => false,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'show_in_nav_menus'     => true,
            'query_var'             => true,
            'rewrite'               => array( 'slug' => $slug, 'with_front' => true ),
            'show_admin_column'     => false,
            'show_in_rest'          => true,
            'show_tagcloud'         => false,
            'rest_base'             => $slug,
            'rest_controller_class' => 'WP_REST_Terms_Controller',
            'rest_namespace'        => 'wp/v2',
            'show_in_quick_edit'    => false,
            'sort'                  => false,
            'show_in_graphql'       => false,
        );

        register_taxonomy( $slug, array( 'activities' ), $args );
    }
}
