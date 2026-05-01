<?php

declare(strict_types=1);

namespace AddTriplestore\Service;

use Omeka\Api\Manager as ApiManager;

/**
 * Omeka REST item_sets / patch updates used by the site upload orchestration layer.
 */
class OmekaSiteResourceService
{
    /** @var ApiManager */
    private $api;

    public function __construct(ApiManager $api)
    {
        $this->api = $api;
    }

    public function createItemSetFromCollectedExcavationForm(string $excavationIdentifier, array $excavationData): int
    {
        return $this->createItemSet($this->buildCollectedExcavationItemSetPayload($excavationIdentifier, $excavationData));
    }

    public function createItemSetFromUploadedTtlMetadata(array $excavationMetadata, string $excavationIdentifier): int
    {
        $itemSetTitle = "Excavation $excavationIdentifier";
        $itemSetDescription = $excavationMetadata['location'] ?
            'Archaeological excavation at ' . $excavationMetadata['location'] :
            'Archaeological excavation with identifier ' . $excavationIdentifier;

        return $this->createItemSet([
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => 1,
                    '@value' => $itemSetTitle,
                ],
            ],
            'dcterms:description' => [
                [
                    'type' => 'literal',
                    'property_id' => 4,
                    '@value' => $itemSetDescription,
                ],
            ],
            'dcterms:creator' => $excavationMetadata['archaeologist'] ? [
                [
                    'type' => 'literal',
                    'property_id' => 7665,
                    '@value' => $excavationMetadata['archaeologist'],
                ],
            ] : [],
            'o:is_public' => true,
        ]);
    }

    /**
     * Applies human-readable titles after batch Omeka ingestion.
     *
     * @param list<array<string, mixed>|object> $createdItems
     */
    public function applyPostIngestionItemTitleUpdates(array $createdItems, bool $isExcavation, string $excavationIdentifier): int
    {
        $updatedCount = 0;

        foreach ($createdItems as $item) {
            $itemId = null;
            if (is_array($item) && isset($item['o:id'])) {
                $itemId = $item['o:id'];
            }
            if (!$itemId) {
                continue;
            }

            if ($isExcavation) {
                $title = "Excavation $excavationIdentifier Item $itemId";
            } else {
                $title = "Arrowhead $itemId"
                    . ($excavationIdentifier ? " (Excavation $excavationIdentifier)" : '');
            }

            try {
                $updateResult = $this->api->update('items', $itemId, [
                    'dcterms:title' => [
                        [
                            'type' => 'literal',
                            'property_id' => 1,
                            '@value' => $title,
                        ],
                    ],
                ], [], ['isPartial' => true]);

                if ($updateResult) {
                    ++$updatedCount;
                }
            } catch (\Exception $e) {
            }
        }

        return $updatedCount;
    }

    /** @param array<string, mixed> $payload Omeka REST item_set representation */
    private function createItemSet(array $payload): int
    {
        try {
            $response = $this->api->create('item_sets', $payload);

            return (int) $response->getContent()->id();
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /** @param array<string, mixed> $excavationData Same shape used by ExcavationTtlBuilder */
    private function buildCollectedExcavationItemSetPayload(string $excavationIdentifier, array $excavationData): array
    {
        $title = "Excavation $excavationIdentifier";
        $description = 'Archaeological excavation';

        if (!empty($excavationData['site_name'])) {
            $description .= ' at ' . $excavationData['site_name'];
        }

        if (!empty($excavationData['location'])) {
            $description .= ' - ' . $excavationData['location'];
        }

        $itemSetData = [
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => 1,
                    '@value' => $title,
                ],
            ],
            'dcterms:description' => [
                [
                    'type' => 'literal',
                    'property_id' => 4,
                    '@value' => $description,
                ],
            ],
            'dcterms:identifier' => [
                [
                    'type' => 'literal',
                    'property_id' => 10,
                    '@value' => $excavationIdentifier,
                ],
            ],
            'o:is_public' => true,
        ];

        if (!empty($excavationData['archaeologist']['name'])) {
            $itemSetData['dcterms:creator'] = [
                [
                    'type' => 'literal',
                    'property_id' => 7665,
                    '@value' => $excavationData['archaeologist']['name'],
                ],
            ];
        }

        return $itemSetData;
    }
}
