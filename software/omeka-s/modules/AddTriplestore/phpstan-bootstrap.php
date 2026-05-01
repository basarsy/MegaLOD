<?php

declare(strict_types=1);

// Omeka S defines OMEKA_PATH at runtime; analysis runs without full bootstrap.
if (!defined('OMEKA_PATH')) {
    define('OMEKA_PATH', realpath(__DIR__ . '/../..') ?: __DIR__ . '/../..');
}
