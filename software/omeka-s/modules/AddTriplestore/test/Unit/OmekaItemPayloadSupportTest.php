<?php

declare(strict_types=1);

namespace AddTriplestore\Test\Unit;

use AddTriplestore\Service\Ingestion\OmekaItemPayloadSupport;
use PHPUnit\Framework\TestCase;

final class OmekaItemPayloadSupportTest extends TestCase
{
    public function testExtractDctermsIdentifierFirstLiteral(): void
    {
        $payload = [
            'dcterms:identifier' => [
                ['@value' => 'CV-001', 'type' => 'literal'],
            ],
        ];
        $this->assertSame('CV-001', OmekaItemPayloadSupport::extractDctermsIdentifier($payload));
    }

    public function testExtractMissingReturnsNull(): void
    {
        $this->assertNull(OmekaItemPayloadSupport::extractDctermsIdentifier([]));
    }
}
