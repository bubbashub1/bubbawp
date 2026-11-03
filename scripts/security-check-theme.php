<?php
/**
 * Fail closed on common theme packaging and code-security mistakes.
 * This is a static guard, not a complete security audit.
 */
declare(strict_types=1);
$root = realpath(__DIR__ . '/../theme/bubba-hub');
if ($root === false) { fwrite(STDERR, "Theme missing\n"); exit(1); }
$errors = [];
$allowedExtensions = ['php', 'html', 'css', 'json', 'md', 'png', 'jpg', 'jpeg', 'webp', 'svg', 'gif', 'ico', 'txt', 'pot'];
$forbiddenNames = ['.env', '.htaccess', '.user.ini', 'wp-config.php', 'composer.lock', 'id_rsa', 'id_ed25519'];
$forbiddenPhp = [
    '/\\b(?:eval|assert|shell_exec|passthru|system|exec|proc_open|popen)\\s*\\(/i',
    '/\\b(?:unserialize|base64_decode|gzinflate)\\s*\\(/i',
    '/\\b(?:file_put_contents|fwrite|move_uploaded_file)\\s*\\(/i',
    '/\\b(?:curl_exec|wp_remote_post|wp_remote_get)\\s*\\(/i',
];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $name = $file->getFilename();
    $path = $file->getPathname();
    $relative = substr($path, strlen($root) + 1);
    if ($file->isLink()) { $errors[] = "Symlink forbidden: $relative"; continue; }
    if (str_starts_with($name, '.') || in_array(strtolower($name), $forbiddenNames, true)) {
        $errors[] = "Hidden or sensitive file: $relative";
    }
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) { $errors[] = "Unexpected extension: $relative"; }
    if ($file->getSize() > 5 * 1024 * 1024) { $errors[] = "Oversized file: $relative"; }
    if ($extension !== 'php') { continue; }
    $source = file_get_contents($path) ?: '';
    foreach ($forbiddenPhp as $pattern) {
        if (preg_match($pattern, $source)) { $errors[] = "Review forbidden PHP operation: $relative"; }
    }
    if (preg_match('/\\$_(?:GET|POST|REQUEST|COOKIE|FILES|SERVER)\\b/', $source)) {
        $errors[] = "Unreviewed request input: $relative";
    }
}
$bootstrap = file_get_contents($root . '/functions.php') ?: '';
if (!str_contains($bootstrap, "defined( 'ABSPATH' )")) {
    $errors[] = 'Theme bootstrap missing direct-access guard';
}
if ($errors) { fwrite(STDERR, implode("\n", $errors) . "\n"); exit(1); }
echo "Static security checks passed (no sensitive files or disallowed operations).\n";
