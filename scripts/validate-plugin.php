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
        if (strpos($source, $unsafe) !== false) {
            fwrite(STDERR, "Unexpected side-effect API in {$file}: {$unsafe}\n");
            exit(1);
        }
    }
}
echo "Plugin foundation static checks passed; WordPress activation QA remains pending.\n";
