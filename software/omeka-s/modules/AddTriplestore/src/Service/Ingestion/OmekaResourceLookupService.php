<?php

namespace AddTriplestore\Service\Ingestion;

use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\ItemRepresentation;

/**
 * Omeka API lookups shared by RDF→Omeka ingestion and site controllers (SRP: item resolution only).
 */
class OmekaResourceLookupService
{
    /** @var ApiManager */
    private $api;

    public function __construct(ApiManager $api)
    {
        $this->api = $api;
    }

    /**
     * Read a single Omeka item by numeric id for site/forms (archaeologist picklist enrichment).
     */
    public function readItem(int $itemId): ?ItemRepresentation
    {
        try {
            return $this->api->read('items', $itemId)->getContent();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Finds an item by identifier (dcterms:identifier or title heuristics), optionally scoped to an item set.
     */
    public function findItemByIdentifier(string $identifier, ?int $itemSetId = null): ?ItemRepresentation
    {
        try {
            $searchVariations = $this->generateIdentifierVariations($identifier);

            foreach ($searchVariations as $searchTerm) {
                $searchParams = [
                    'property' => [
                        [
                            'property' => 10,
                            'type' => 'eq',
                            'text' => $searchTerm,
                        ],
                    ],
                    'limit' => 10,
                ];

                if ($itemSetId) {
                    $searchParams['item_set_id'] = $itemSetId;
                }

                $response = $this->api->search('items', $searchParams);
                $items = $response->getContent();

                if (!empty($items)) {
                    foreach ($items as $item) {
                        if ($itemSetId) {
                            foreach ($item->itemSets() as $itemSet) {
                                if ($itemSet->id() == $itemSetId) {
                                    return $item;
                                }
                            }
                        } else {
                            return $items[0];
                        }
                    }
                }

                $titleSearchParams = [
                    'property' => [
                        [
                            'property' => 1,
                            'type' => 'in',
                            'text' => $searchTerm,
                        ],
                    ],
                    'limit' => 10,
                ];

                if ($itemSetId) {
                    $titleSearchParams['item_set_id'] = $itemSetId;
                }

                $response = $this->api->search('items', $titleSearchParams);
                $items = $response->getContent();

                if (!empty($items)) {
                    foreach ($items as $item) {
                        if ($itemSetId) {
                            foreach ($item->itemSets() as $itemSet) {
                                if ($itemSet->id() == $itemSetId) {
                                    return $item;
                                }
                            }
                        } else {
                            return $items[0];
                        }
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Whether any item has this exact dcterms:identifier (property 10, eq), regardless of item set.
     */
    public function itemExistsWithDctermsIdentifier(string $identifier): bool
    {
        if ($identifier === '') {
            return false;
        }

        try {
            $response = $this->api->search('items', [
                'property' => [
                    [
                        'property' => 10,
                        'type' => 'eq',
                        'text' => $identifier,
                    ],
                ],
                'limit' => 1,
            ]);

            return $response->getTotalResults() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Whether an item in the given item set already has this exact dcterms:identifier value (property 10, eq).
     * Used for REST create idempotency — unlike findItemByIdentifier(), this does not try variations or title search.
     */
    public function itemExistsWithDctermsIdentifierInItemSet(string $identifier, int $itemSetId): bool
    {
        try {
            $searchParams = [
                'property' => [
                    [
                        'property' => 10,
                        'type' => 'eq',
                        'text' => $identifier,
                    ],
                ],
                'item_set_id' => $itemSetId,
                'limit' => 1,
            ];
            $response = $this->api->search('items', $searchParams);

            return $response->getTotalResults() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    public function generateIdentifierVariations(string $identifier): array
    {
        $variations = [$identifier];

        $prefixesToTry = ['Context-', 'SVU-', 'Square-', 'EXC-', 'CV-'];
        foreach ($prefixesToTry as $prefix) {
            if (strpos($identifier, $prefix) === 0) {
                $withoutPrefix = substr($identifier, strlen($prefix));
                $variations[] = $withoutPrefix;

                if ($prefix === 'Context-') {
                    $variations[] = 'CV-' . $withoutPrefix;
                }
            }
        }

        if (preg_match('/^\d+$/', $identifier)) {
            $variations[] = 'CV-' . str_pad($identifier, 3, '0', STR_PAD_LEFT);
            $variations[] = 'CV-001-' . $identifier;

            if ($identifier <= 26) {
                $letter = chr(64 + ($identifier % 26 + 1));
                $number = ceil($identifier / 26);
                $variations[] = $letter . $number;
            }
        }

        return array_values(array_unique($variations));
    }

    /**
     * @param int|string $itemId
     */
    public function getRealIdentifierFromOmekaItem($itemId): string
    {
        try {
            $itemId = (int) $itemId;
            $item = $this->api->read('items', $itemId)->getContent();

            $values = $item->values();

            if (isset($values['dcterms:identifier'])) {
                foreach ($values['dcterms:identifier'] as $value) {
                    if ($value instanceof \Omeka\Api\Representation\ValueRepresentation) {
                        return $value->value();
                    }
                }
            }

            $title = $item->displayTitle();

            $patterns = [
                '/Stratigraphic Unit\s+([A-Za-z0-9\-_]+)/',
                '/Context\s+([A-Za-z0-9\-_]+)/',
                '/Square\s+([A-Za-z0-9\-_]+)/',
                '/Arrowhead\s+([A-Za-z0-9\-_]+)/',
                '/\b(Layer-\d+)\b/',
                '/\b(CTX-\d+)\b/',
                '/\b(CV-\d+-\d+)\b/',
                '/\b(CV-\d+)\b/',
                '/\b([A-Z]\d+)\b/',
                '/\b(AH-[A-Z0-9]+)\b/',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $title, $matches)) {
                    return $matches[1];
                }
            }

            $resourceClass = $item->resourceClass();
            if ($resourceClass) {
                $className = $resourceClass->label();

                switch (strtolower($className)) {
                    case 'context':
                        return 'CTX-' . str_pad((string) ($itemId % 1000), 3, '0', STR_PAD_LEFT);
                    case 'stratigraphic unit':
                    case 'svu':
                    case 'stratigraphic volume unit':
                        return 'Layer-' . str_pad((string) ($itemId % 100), 2, '0', STR_PAD_LEFT);
                    case 'square':
                        $letters = ['A', 'B', 'C', 'D'];
                        $letter = $letters[($itemId - 1) % 4];
                        $number = (($itemId - 1) % 4) + 1;

                        return $letter . $number;
                    case 'arrowhead':
                        return 'AH-' . str_pad((string) ($itemId % 1000), 3, '0', STR_PAD_LEFT);
                    default:
                        return 'ITEM-' . $itemId;
                }
            }

            return 'ITEM-' . $itemId;
        } catch (\Exception $e) {
            return 'ITEM-' . $itemId;
        }
    }
}
