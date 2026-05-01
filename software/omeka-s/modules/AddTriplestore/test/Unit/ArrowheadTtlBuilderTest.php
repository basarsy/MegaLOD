<?php

declare(strict_types=1);

namespace AddTriplestore\Test\Unit;

use AddTriplestore\Service\Ingestion\OmekaResourceLookupService;
use AddTriplestore\Service\Ttl\ArrowheadTtlBuilder;
use AddTriplestore\Service\Ttl\TtlUriHelper;
use PHPUnit\Framework\TestCase;

final class ArrowheadTtlBuilderTest extends TestCase
{
    public function testBuildEmitsSingleFoundInLocationAndPlaceholder(): void
    {
        $lookup = $this->createMock(OmekaResourceLookupService::class);
        $lookup->method('getRealIdentifierFromOmekaItem')->willReturn('S1');

        $builder = new ArrowheadTtlBuilder(new TtlUriHelper(), $lookup, 'https://local.example/');
        $ttl = $builder->buildFromFormData(
            [
                'arrowhead_identifier' => 'AH-1',
                'selected_square' => 5,
                'location' => 'https://placeholder/location',
            ],
            '10',
            'EX1',
            null,
            null
        );

        $this->assertStringContainsString('excav:foundInLocation <https://placeholder/location>', $ttl);
        $this->assertSame(1, substr_count($ttl, 'excav:foundInLocation'));
        $this->assertStringContainsString('ah:Arrowhead', $ttl);
    }
}
