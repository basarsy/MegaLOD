<?php

declare(strict_types=1);

namespace AddTriplestore\Test\Unit;

use AddTriplestore\Service\Ingestion\CollectingFormToExcavationDataMapper;
use AddTriplestore\Service\Ingestion\OmekaResourceLookupService;
use PHPUnit\Framework\TestCase;

final class CollectingFormToExcavationDataMapperTest extends TestCase
{
    public function testMapsFieldPromptsAndArchaeologistWhenNewArchaeologist(): void
    {
        $lookup = $this->createMock(OmekaResourceLookupService::class);
        $lookup->expects($this->never())->method('readItem');

        $m = new CollectingFormToExcavationDataMapper($lookup);

        $out = $m->mapFromPost([
            'prompt_32' => 'TST',
            'prompt_35' => 'Somewhere',
            'new_archaeologist_name' => 'Alice',
            'new_archaeologist_orcid' => '0000-0002-0000-0000',
            'new_archaeologist_email' => 'a@example.test',
        ]);

        $this->assertSame('TST', $out['excavation_id']);
        $this->assertSame('Somewhere', $out['site_name']);
        $this->assertSame('Alice', $out['archaeologist']['name']);
        $this->assertSame('0000-0002-0000-0000', $out['archaeologist']['orcid']);
        $this->assertSame('a@example.test', $out['archaeologist']['email']);
        $this->assertFalse((bool) $out['archaeologist']['existing']);
    }
}
