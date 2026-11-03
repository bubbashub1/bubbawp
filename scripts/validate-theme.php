<?php
/**
 * Static WordPress block theme checks.
 * These checks do not replace WordPress activation, browser or accessibility QA.
 */
declare(strict_types=1);

$root = dirname(__DIR__) . '/theme/bubba-hub';
$errors = [];
$required = [
    'style.css', 'theme.json', 'functions.php', 'assets/css/site.css',
    'templates/index.html', 'templates/front-page.html', 'templates/page.html',
    'templates/single.html', 'templates/archive.html', 'templates/search.html',
    'templates/404.html', 'templates/activity-preview.html', 'parts/header.html', 'parts/footer.html',
    'patterns/hero.php', 'patterns/discover.php', 'patterns/join.php',
    'patterns/category-grid.php', 'patterns/featured-placeholder.php',
    'patterns/event-promo.php', 'patterns/directory-shell.php',
    'patterns/activity-design-preview.php',
];
foreach ($required as $file) {
    if (!is_file($root . '/' . $file) || filesize($root . '/' . $file) === 0) {
        $errors[] = 'Missing or empty: ' . $file;
    }
}
if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

$css = file_get_contents($root . '/style.css') ?: '';
// Validate actual WordPress theme metadata, not just the presence of labels.
$headers = [];
if (preg_match_all('/^\\s*\\*?\\s*(Theme Name|Version|Text Domain|Requires at least|Requires PHP):\\s*(.+)$/mi', $css, $headerMatches, PREG_SET_ORDER)) {
    foreach ($headerMatches as $match) {
        $headers[strtolower($match[1])] = trim($match[2]);
    }
}
if (($headers['theme name'] ?? '') !== 'Bubba Hub') {
    $errors[] = 'Unexpected or missing Theme Name header';
}
if (!preg_match('/^\\d+\\.\\d+\\.\\d+(?:[-+][A-Za-z0-9.-]+)?$/', $headers['version'] ?? '')) {
    $errors[] = 'Theme Version must be a semantic version';
}
if (($headers['text domain'] ?? '') !== 'bubba-hub') {
    $errors[] = 'Text Domain must match the theme slug';
}
if (!preg_match('/^\\d+\\.\\d+$/', $headers['requires at least'] ?? '')) {
    $errors[] = 'Requires at least must specify a WordPress version';
}
if (!preg_match('/^\\d+\\.\\d+$/', $headers['requires php'] ?? '')) {
    $errors[] = 'Requires PHP must specify a PHP version';
}

foreach (['Theme Name:', 'Version:', 'Text Domain:'] as $field) {
    if (strpos($css, $field) === false) {
        $errors[] = 'Missing style.css header: ' . $field;
    }
}

$json = json_decode(file_get_contents($root . '/theme.json') ?: '', true);
if (!is_array($json) || json_last_error() !== JSON_ERROR_NONE) {
    $errors[] = 'Invalid theme.json';
} else {
    if (($json['version'] ?? null) !== 2 || !isset($json['settings'], $json['styles'])) {
        $errors[] = 'theme.json must contain supported version 2, settings and styles';
    }
    if (!in_array('activity-preview', array_column($json['customTemplates'] ?? [], 'name'), true)) {
        $errors[] = 'Activity preview template missing theme.json registration';
    }
    foreach (['header', 'footer'] as $part) {
        if (!in_array($part, array_column($json['templateParts'] ?? [], 'name'), true)) {
            $errors[] = 'Missing theme.json template part registration: ' . $part;
        }
    }
}

$patterns = [];
foreach (glob($root . '/patterns/*.php') ?: [] as $path) {
    $source = file_get_contents($path) ?: '';
    if (!preg_match('~\*\s*Slug:\s*(bubba-hub/[a-z0-9-]+)~', $source, $match)) {
        $errors[] = 'Missing/invalid pattern Slug header: ' . basename($path);
        continue;
    }
    if (isset($patterns[$match[1]])) {
        $errors[] = 'Duplicate pattern slug: ' . $match[1];
    }
    $patterns[$match[1]] = true;
    if (!preg_match('~\*\s*Title:\s*\S+~', $source)) {
        $errors[] = 'Missing pattern Title header: ' . basename($path);
    }
}

$parts = [];
foreach (glob($root . '/parts/*.html') ?: [] as $path) {
    $parts[pathinfo($path, PATHINFO_FILENAME)] = true;
}
foreach (glob($root . '/templates/*.html') ?: [] as $path) {
    $source = file_get_contents($path) ?: '';
    $filename = basename($path);
    if (strpos($source, '<!-- wp:') === false) {
        $errors[] = 'Missing block markup: ' . $filename;
    }
    preg_match_all('~<!--\s+wp:template-part\s+({.*?})\s*/-->~', $source, $matches);
    if (!$matches[1]) {
        $errors[] = 'Missing template-part reference: ' . $filename;
    }
    foreach ($matches[1] as $raw) {
        $attrs = json_decode($raw, true);
        $slug = $attrs['slug'] ?? '';
        if (!isset($parts[$slug])) {
            $errors[] = 'Unknown template part ' . $slug . ' in ' . $filename;
        }
    }
    preg_match_all('~<!--\s+wp:pattern\s+({.*?})\s*/-->~', $source, $matches);
    foreach ($matches[1] as $raw) {
        $attrs = json_decode($raw, true);
        $slug = $attrs['slug'] ?? '';
        if (!isset($patterns[$slug])) {
            $errors[] = 'Unknown pattern ' . $slug . ' in ' . $filename;
        }
    }
}
if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}
echo 'Static theme checks passed: ' . count($required) . ' required files, ' .
    count($patterns) . ' patterns and ' . count($parts) . " template parts.\n" .
    "WordPress activation, Site Editor and visual QA still required.\n";
