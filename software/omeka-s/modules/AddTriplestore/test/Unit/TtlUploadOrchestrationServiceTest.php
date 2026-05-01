<?php

declare(strict_types=1);

namespace AddTriplestore\Test\Unit;

use AddTriplestore\Service\Encounter\EncounterEventService;
use AddTriplestore\Service\ExcavationItemSetContextService;
use AddTriplestore\Service\GraphDbHttpService;
use AddTriplestore\Service\Ingestion\OmekaIngestionService;
use AddTriplestore\Service\Ingestion\OmekaResourceLookupService;
use AddTriplestore\Service\Ingestion\OmekaRestSubmissionService;
use AddTriplestore\Service\MegalodConfig;
use AddTriplestore\Service\OmekaSiteResourceService;
use AddTriplestore\Service\Ttl\TtlContentInspectionService;
use AddTriplestore\Service\Ttl\TtlUriNormalizer;
use AddTriplestore\Service\Upload\TtlUploadOrchestrationService;
use PHPUnit\Framework\TestCase;

final class TtlUploadOrchestrationServiceTest extends TestCase
{
    public function testReturnsGraphFailureWhenShaclGatewayDoesNotSucceed(): void
    {
        $meg = new MegalodConfig(
            'http://graphdb.invalid/',
            'test-repo',
            'https://example.invalid/',
            'http://megalod.local.invalid/',
            'http://workbench.invalid/'
        );

        $graphDb = $this->createMock(GraphDbHttpService::class);
        $graphDb->method('uploadAfterShaclValidation')->willReturn('rejected-by-graphdb');

        $normalizer = $this->createMock(TtlUriNormalizer::class);
        $normalizer->method('normalizeUris')->willReturnArgument(0);

        $encounter = $this->createMock(EncounterEventService::class);
        $encounter->method('applyEncounterForArrowheadUpload')->willReturn([
            'ok' => true,
            'ttl' => '@prefix ex: <http://ex/>. <> a ex:A . ',
        ]);

        $ttlInspect = $this->createMock(TtlContentInspectionService::class);
        $ttlInspect->method('validateUploadType')->willThrowException(new \RuntimeException('not excavation'));

        $lookup = $this->createMock(OmekaResourceLookupService::class);
        $excavCtx = $this->createMock(ExcavationItemSetContextService::class);
        $excavCtx->method('getExcavationIdentifierFromItemSet')->willReturn(null);

        $ingest = $this->createMock(OmekaIngestionService::class);
        $rest = $this->createMock(OmekaRestSubmissionService::class);

        $omekaSite = $this->createMock(OmekaSiteResourceService::class);
        $omekaSite->expects($this->never())->method('applyPostIngestionItemTitleUpdates');

        $svc = new TtlUploadOrchestrationService(
            $meg,
            $graphDb,
            $normalizer,
            $encounter,
            $ttlInspect,
            $lookup,
            $excavCtx,
            $ingest,
            $rest,
            $omekaSite
        );

        $msg = $svc->uploadTtlData('@prefix z: <> .', 1, null, null);

        $this->assertStringContainsString('Failed to upload data to GraphDB', $msg);
    }
}
