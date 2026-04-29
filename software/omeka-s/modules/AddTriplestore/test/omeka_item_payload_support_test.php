<?php

/**
 * Lightweight regression checks for OmekaItemPayloadSupport.
 *
 * From repo root:
 *   php software/omeka-s/modules/AddTriplestore/test/omeka_item_payload_support_test.php
 * From the AddTriplestore module directory:
 *   php test/omeka_item_payload_support_test.php
 */

declare(strict_types=1);

$base = dirname(__DIR__);
require_once $base . '/src/Service/Ingestion/OmekaItemPayloadSupport.php';

use AddTriplestore\Service\Ingestion\OmekaItemPayloadSupport;

$fail = static function (string $msg): void {
    fwrite(STDERR, "FAIL: $msg\n");
    exit(1);
};

$payload = [
    'dcterms:identifier' => [
        ['@value' => 'CV-001', 'type' => 'literal'],
    ],
];
if (OmekaItemPayloadSupport::extractDctermsIdentifier($payload) !== 'CV-001') {
    $fail('expected first @value');
}

if (OmekaItemPayloadSupport::extractDctermsIdentifier([]) !== null) {
    $fail('empty payload should be null');
}

if (OmekaItemPayloadSupport::extractDctermsIdentifier(['dcterms:identifier' => 'bad']) !== null) {
    $fail('non-array identifier should be null');
}

fwrite(STDOUT, "ok\n");
