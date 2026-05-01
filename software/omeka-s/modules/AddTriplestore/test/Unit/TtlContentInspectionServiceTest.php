<?php

declare(strict_types=1);

namespace AddTriplestore\Test\Unit;

use AddTriplestore\Service\Ttl\TtlContentInspectionService;
use PHPUnit\Framework\TestCase;

final class TtlContentInspectionServiceTest extends TestCase
{
    public function testExtractExcavationIdentifierDctPrefixedLiteral(): void
    {
        $svc = new TtlContentInspectionService();
        $ttl = 'dct:identifier "EX-99"^^xsd:literal .';
        $this->assertSame('EX-99', $svc->extractExcavationIdentifier($ttl));
    }

    public function testExtractExcavationMetadata(): void
    {
        $svc = new TtlContentInspectionService();
        $ttl = 'dbo:informationName "Test Site" . foaf:name "Ada" . ';
        $m = $svc->extractExcavationMetadataFromTtl($ttl);
        $this->assertSame('Test Site', $m['location']);
        $this->assertSame('Ada', $m['archaeologist']);
    }

    public function testValidateUploadTypeArrowheadRejectsExcavationTtl(): void
    {
        $svc = new TtlContentInspectionService();
        $ttl = '<x> a excav:Excavation . ';
        $this->expectException(\Exception::class);
        $svc->validateUploadType($ttl, 'arrowhead');
    }

    public function testValidateExcavationAllowsArrowheadContent(): void
    {
        $svc = new TtlContentInspectionService();
        $ttl = '<x> a ah:Arrowhead . ';
        $svc->validateUploadType($ttl, 'excavation');
        $this->addToAssertionCount(1);
    }
}
