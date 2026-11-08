<?php
/**
 * Plugin Name: Bubba Hub Manager 3.0
 * Description: Safe, modular foundation for Bubba Hub 3.0. Functional modules are disabled until parity testing.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: Bubba Hub South West
 * License: GPL-2.0-or-later
 * Text Domain: bubba-hub-manager
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
define( 'BUBBA_HUB_MANAGER_VERSION', '0.1.0' );
define( 'BUBBA_HUB_MANAGER_PATH', plugin_dir_path( __FILE__ ) );
require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-manager.php';
require_once BUBBA_HUB_MANAGER_PATH . 'includes/class-bh-development-tracker.php';
add_action( 'plugins_loaded', array( 'BH_Manager_3', 'boot' ) );

/* Staging-only, read-only compatibility diagnostics. */
if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk' ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-bh-events-manager-bridge.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-bh-events-manager-diagnostics.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-bh-ninja-forms-diagnostics.php';
    add_action( 'plugins_loaded', array( 'BH_Events_Manager_Diagnostics', 'register' ), 20 );
    add_action( 'plugins_loaded', array( 'BH_Ninja_Forms_Diagnostics', 'register' ), 20 );
}
