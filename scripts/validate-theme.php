<?php
/**
 * Static theme checks (no WordPress runtime needed).
 * Does not replace a staging activation and visual review.
 */
declare(strict_types=1);

$root = dirname(__DIR__) . '/theme/bubba-hub';
$required = [
    'style.css', 'theme.json', 'functions.php',
    'templates/index.html', 'templates/front-page.html',
    'templates/page.html', 'templates/single.html',
    'templates/archive.html', 'templates/search.html',
    'templates/404.html',
    'parts/header.html', 'parts/footer.html',
    'patterns/hero.php', 'patterns/discover.php', 'patterns/join.php',
];
$errors = [];
foreach ($required as $file) {
    if (!is_file($root . '/' . $file) || filesize($root . '/' . $file) === 0) {
        $errors[] = 'Missing/empty: ' . $file;
    }
}
$css = file_get_contents($root . '/style.css') ?: '';
foreach (['Theme Name:', 'Version:', 'Text Domain:'] as $field) {
    if (strpos($css, $field) === false) {
        $errors[] = 'Missing style.css header: ' . $field;
    }
}
$json = json_decode(file_get_contents($root . '/theme.json') ?: '', true);
if (!is_array($json) || json_last_error() !== JSON_ERROR_NONE) {
    $errors[] = 'theme.json is invalid JSON';
} else {
    if (!isset($json['version'], $json['settings'], $json['styles'])) {
        $errors[] = 'theme.json missing essential settings';
    }
}
foreach (glob($root . '/templates/*.html') ?: [] as $path) {
    $markup = file_get_contents($path) ?: '';
    if (strpos($markup, '<!-- wp:') === false) {
        $errors[] = 'No block markup: ' . basename($path);
    }
    if (strpos($markup, 'template-part') === false) {
        $errors[] = 'No template part: ' . basename($path);
    }
}
if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}
echo "Static theme checks passed. WordPress installation and visual QA still required.\n";
