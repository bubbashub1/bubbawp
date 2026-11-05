<?php
/** Static guard for the deliberately inert 3.0 plugin foundation. */
declare(strict_types=1);
$root = dirname(__DIR__) . '/plugin/bubba-hub-manager';
$required = ['bubba-hub-manager.php', 'includes/class-bh-manager.php', 'README.md'];
foreach ($required as $file) {
    if (!is_file($root . '/' . $file) || filesize($root . '/' . $file) === 0) {
        fwrite(STDERR, "Missing plugin file: {$file}\n");
        exit(1);
    }
}
$bootstrap = file_get_contents($root . '/bubba-hub-manager.php');
foreach (['Plugin Name:', 'Version:', 'Requires PHP:', 'Text Domain:'] as $header) {
    if (strpos($bootstrap, $header) === false) {
        fwrite(STDERR, "Missing plugin header: {$header}\n");
        exit(1);
    }
}
foreach (glob($root . '/**/*.php') ?: [] as $file) {
    $source = file_get_contents($file);
    foreach (['register_activation_hook(', 'dbDelta(', 'wp_schedule_event(', 'register_rest_route('] as $unsafe) {
        // The explicit schema installer may use dbDelta, but never on activation.
        if ($unsafe === 'dbDelta(' && basename($file) === 'class-bh-schema.php' &&
            strpos($source, 'public static function install()') !== false &&
            strpos($source, "current_user_can( 'manage_options' )") !== false) {
            continue;
        }
        // The staging-only Field Manager installer also requires an explicit admin action,
        // a nonce and a confirmation phrase. It never runs on activation.
        if ($unsafe === 'dbDelta(' && basename($file) === 'class-bh-field-manager.php' &&
            strpos($source, 'public static function install()') !== false &&
            strpos($source, "self::guard( 'bh3_field_install' )") !== false &&
            strpos($source, "!== 'INSTALL'") !== false &&
            strpos($source, "staging.bubbahub.co.uk") !== false) {
            continue;
        }
        // Ask Support schema is an explicit, staging-only administrator action.
        // Keep this narrowly scoped: no activation hook, nonce and typed confirmation required.
        if ($unsafe === 'dbDelta(' && basename($file) === 'class-bh-ask-support-storage.php' &&
            strpos($source, 'public static function install(): void') !== false &&
            strpos($source, "current_user_can('manage_options')") !== false &&
            strpos($source, "check_admin_referer('bh3_ask_install_schema')") !== false &&
            strpos($source, "!== 'INSTALL'") !== false &&
            strpos($source, "staging.bubbahub.co.uk") !== false &&
            strpos($source, "add_action('admin_post_bh3_ask_install_schema'") !== false) {
            continue;
        }
        // Family tables are created only through the staging administrator's
        // explicit, nonce-protected admin-post action after backup confirmation.
        // Keep this exception file-specific; activation and REST side effects
        // remain forbidden by the other checks.
        if ($unsafe === 'dbDelta(' && basename($file) === 'class-bh-family-db-setup.php' &&
            strpos($source, "add_action('admin_post_bh3_create_family_tables',array(__CLASS__,'create'))") !== false &&
            strpos($source, 'public static function create()') !== false &&
            strpos($source, "!self::staging() || !current_user_can('manage_options')") !== false &&
            strpos($source, "check_admin_referer('bh3_create_family_tables')") !== false &&
            strpos($source, 'if (!isset($_POST[\'confirm\']) || $_POST[\'confirm\']!==\'1\')') !== false &&
            strpos($source, 'if (self::exists($table)) continue;') !== false &&
            strpos($source, "staging.bubbahub.co.uk") !== false) {
            continue;
        }
        // Marketplace REST endpoints are registered on rest_api_init only on staging.
        // Booking routes require login and an explicit feature flag; the public
        // Stripe test webhook is gated and verifies the signed payload.
        if ($unsafe === 'register_rest_route(' && basename($file) === 'class-bh-marketplace-settings.php' &&
            strpos($source, "staging.bubbahub.co.uk") !== false &&
            strpos($source, "add_action( 'rest_api_init', array( __CLASS__, 'register_test_webhook_route' ) )") !== false &&
            strpos($source, "add_action( 'rest_api_init', array( __CLASS__, 'register_parent_booking_binding_route' ) )") !== false &&
            strpos($source, "add_action( 'rest_api_init', array( __CLASS__, 'register_bound_parent_checkout_route' ) )") !== false &&
            strpos($source, 'BH3_ENABLE_BOOKING_FORM_BINDING') !== false &&
            strpos($source, 'BH3_ENABLE_STRIPE_TEST_WEBHOOK') !== false &&
            strpos($source, "'permission_callback' => function () { return is_user_logged_in(); }") !== false &&
            strpos($source, "'permission_callback' => '__return_true'") !== false &&
            strpos($source, 'verify_test_webhook(') !== false) {
            continue;
        }
        if (strpos($source, $unsafe) !== false) {
            fwrite(STDERR, "Unexpected side-effect API in {$file}: {$unsafe}\n");
            exit(1);
        }
    }
}
echo "Plugin foundation static checks passed; WordPress activation QA remains pending.\n";
