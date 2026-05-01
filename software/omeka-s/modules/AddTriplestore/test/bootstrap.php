<?php

declare(strict_types=1);

$vendorAutoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (!is_file($vendorAutoload)) {
    fwrite(STDERR, "Missing vendor/autoload.php — run `composer install` in software/omeka-s.\n");
    exit(1);
}
require $vendorAutoload;

// Services log under OMEKA_PATH; define for unit tests without a full Omeka bootstrap.
if (!defined('OMEKA_PATH')) {
    define('OMEKA_PATH', dirname(__DIR__, 3));
}
$logsDir = OMEKA_PATH . '/logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0777, true);
}

spl_autoload_register(static function (string $class): bool {
    if (strncmp($class, 'AddTriplestore\\', 15) !== 0) {
        return false;
    }
    $relative = substr($class, strlen('AddTriplestore\\'));
    $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;

        return true;
    }

    return false;
});
