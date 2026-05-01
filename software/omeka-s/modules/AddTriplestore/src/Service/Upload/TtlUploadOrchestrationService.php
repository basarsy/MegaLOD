<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Upload;

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

final class TtlUploadOrchestrationService
{
    /** @var MegalodConfig */
    private $megalodConfig;

    /** @var GraphDbHttpService */
    private $graphDbHttpService;

    /** @var TtlUriNormalizer */
    private $ttlUriNormalizer;

    /** @var EncounterEventService */
    private $encounterEventService;

    /** @var TtlContentInspectionService */
    private $ttlContentInspectionService;

    /** @var OmekaResourceLookupService */
    private $omekaResourceLookupService;

    /** @var ExcavationItemSetContextService */
    private $excavationItemSetContextService;

    /** @var OmekaIngestionService */
    private $omekaIngestionService;

    /** @var OmekaRestSubmissionService */
    private $omekaRestSubmissionService;

    /** @var OmekaSiteResourceService */
    private $omekaSiteResourceService;

    public function __construct(
        MegalodConfig $megalodConfig,
        GraphDbHttpService $graphDbHttpService,
        TtlUriNormalizer $ttlUriNormalizer,
        EncounterEventService $encounterEventService,
        TtlContentInspectionService $ttlContentInspectionService,
        OmekaResourceLookupService $omekaResourceLookupService,
        ExcavationItemSetContextService $excavationItemSetContextService,
        OmekaIngestionService $omekaIngestionService,
        OmekaRestSubmissionService $omekaRestSubmissionService,
        OmekaSiteResourceService $omekaSiteResourceService
    ) {
        $this->megalodConfig = $megalodConfig;
        $this->graphDbHttpService = $graphDbHttpService;
        $this->ttlUriNormalizer = $ttlUriNormalizer;
        $this->encounterEventService = $encounterEventService;
        $this->ttlContentInspectionService = $ttlContentInspectionService;
        $this->omekaResourceLookupService = $omekaResourceLookupService;
        $this->excavationItemSetContextService = $excavationItemSetContextService;
        $this->omekaIngestionService = $omekaIngestionService;
        $this->omekaRestSubmissionService = $omekaRestSubmissionService;
        $this->omekaSiteResourceService = $omekaSiteResourceService;
    }

    /**
     * @param mixed[]|null $uploadedFiles Passed through to REST submission layer
     * @param mixed[]|null $excavationData Context for Omeka ingestion
     */
    public function uploadTtlData(
        string $ttlData,
        ?int $itemSetId,
        ?array $uploadedFiles,
        ?array $excavationData
    ): string {
        try {
            $isExcavation = false;
            $excavationIdentifier = '0';

            try {
                $this->ttlContentInspectionService->validateUploadType($ttlData, 'excavation');
                $isExcavation = true;
                $extractedId = $this->ttlContentInspectionService->extractExcavationIdentifier($ttlData);
                if ($extractedId) {
                    $excavationIdentifier = $extractedId;
                }
            } catch (\Exception $e) {
                if ($itemSetId !== null) {
                    $excavationRowId = $this->excavationItemSetContextService->getExcavationIdentifierFromItemSet($itemSetId);
                    if ($excavationRowId) {
                        $excavationIdentifier = $excavationRowId;
                    }
                }
            }

            if ($itemSetId !== null) {
                $ttlData = $this->ttlUriNormalizer->normalizeUris(
                    $ttlData,
                    $itemSetId,
                    function ($setId) {
                        return $this->excavationItemSetContextService->getExcavationIdentifierFromItemSet($setId);
                    }
                );
            } elseif ($isExcavation && $excavationIdentifier) {
                if ($this->omekaResourceLookupService->itemExistsWithDctermsIdentifier($excavationIdentifier)) {
                }

                $excavationMetadata = $this->ttlContentInspectionService->extractExcavationMetadataFromTtl($ttlData);

                try {
                    $newId = $this->omekaSiteResourceService->createItemSetFromUploadedTtlMetadata(
                        $excavationMetadata,
                        $excavationIdentifier
                    );
                    $itemSetId = $newId;

                    $ttlData = $this->ttlUriNormalizer->normalizeUris(
                        $ttlData,
                        $itemSetId,
                        function ($setId) {
                            return $this->excavationItemSetContextService->getExcavationIdentifierFromItemSet($setId);
                        }
                    );

                    $this->excavationItemSetContextService->storeMappingBetweenItemSetAndExcavation($itemSetId, $excavationIdentifier);
                } catch (\Exception $e) {
                    return 'Error: Failed to create excavation item set - ' . $e->getMessage();
                }
            }

            $encounterResult = $this->encounterEventService->applyEncounterForArrowheadUpload(
                $ttlData,
                $itemSetId !== null && $itemSetId !== '' ? (int) $itemSetId : null
            );
            if (!$encounterResult['ok']) {
                return (string) $encounterResult['error'];
            }
            $ttlData = $encounterResult['ttl'];

            error_log('ttldata: ' . $ttlData, 3, OMEKA_PATH . '/logs/normalizeeeee_uris.log');

            $graphUriSuffix = ($itemSetId !== null && $itemSetId !== '') ? (string) $itemSetId : '0';
            $graphUri = $this->megalodConfig->getMegalodPublicBaseUri() . $graphUriSuffix . '/';
            $graphDbResult = $this->graphDbHttpService->uploadAfterShaclValidation($graphUri, $ttlData);

            if (strpos((string) $graphDbResult, 'successfully') !== false) {
                $resolvedExcavationId = $itemSetId ? $this->excavationItemSetContextService->getExcavationIdentifierFromItemSet($itemSetId) : null;
                $omekaData = $this->omekaIngestionService->transformTtlToOmekaItemPayloads(
                    $ttlData,
                    $itemSetId,
                    $resolvedExcavationId
                );

                $itemSetIdInt = $itemSetId !== null && $itemSetId !== '' ? (int) $itemSetId : null;
                $omekaResponse = $this->omekaRestSubmissionService->submitItemPayloads(
                    $omekaData,
                    $itemSetIdInt,
                    $uploadedFiles,
                    $excavationData
                );

                if (empty($omekaResponse['errors'])) {
                    $createdItems = $omekaResponse['created_items'];

                    $this->omekaSiteResourceService->applyPostIngestionItemTitleUpdates(
                        $createdItems,
                        $isExcavation,
                        $excavationIdentifier
                    );

                    if ($isExcavation && $itemSetId !== null && $itemSetId !== '') {
                        return "Data uploaded successfully to both GraphDB and Omeka S. Created Item Set #{$itemSetId} for excavation '{$excavationIdentifier}' and "
                            . count($createdItems)
                            . ' items with updated titles.';
                    }

                    return 'Data uploaded successfully to both GraphDB and Omeka S. Created '
                        . count($createdItems)
                        . ' items with updated titles and proper resource links within excavation context.';
                }

                return 'Data uploaded to GraphDB, but Omeka S errors: '
                    . implode('; ', $omekaResponse['errors']);
            }

            return 'Failed to upload data to GraphDB: ' . $graphDbResult;
        } catch (\Throwable $e) {
            return 'Unexpected error during upload processing: ' . $e->getMessage();
        }
    }
}
