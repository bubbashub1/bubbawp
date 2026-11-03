<?php
/**
 * Bubba Hub native block theme bootstrap.
 *
 * @package BubbaHubTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action(
    'after_setup_theme',
    static function () {
        add_theme_support( 'wp-block-styles' );
        add_theme_support( 'responsive-embeds' );
        add_theme_support( 'editor-styles' );
        add_editor_style( 'assets/css/site.css' );
        add_theme_support( 'post-thumbnails' );
    }
);

add_action(
    'wp_enqueue_scripts',
    static function () {
        wp_enqueue_style(
            'bubba-hub-theme',
            get_theme_file_uri( 'assets/css/site.css' ),
            array(),
            wp_get_theme()->get( 'Version' )
        );
    }
);
