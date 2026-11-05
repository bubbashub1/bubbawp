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
