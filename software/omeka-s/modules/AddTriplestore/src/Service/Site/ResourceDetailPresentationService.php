<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Site;

use AddTriplestore\Service\Ttl\VocabularyLabelService;
use Omeka\Api\Manager as ApiManager;

/**
 * Loads Omeka resources and shapes property/media data for the view-details page.
 *
 * Product note: when the URL type is `item_set`, the code may show a member **item** as the main
 * resource if an “excavation”-labeled item is found, or — when none match — the **first** item in
 * the set (see loadItemSetBranch). An older controller bug (`!$x->resourceName() === 'items'`)
 * prevented the “first item” fallback from ever running; behavior here matches the intended fix.
 * Stakeholders should confirm this matches curator expectations.
 */
final class ResourceDetailPresentationService
{
    /** @var ApiManager */
    private $api;

    /** @var VocabularyLabelService */
    private $vocabularyLabelService;

    public function __construct(ApiManager $api, VocabularyLabelService $vocabularyLabelService)
    {
        $this->api = $api;
        $this->vocabularyLabelService = $vocabularyLabelService;
    }

    public function buildPresentation(string $requestedResourceType, int $requestedId): ResourceDetailPresentationData
    {
        $resourceToDisplay = null;
        $itemSetIdForLink = null;
        $properties = [];
        $relatedItems = [];
        $media = [];

        if ($requestedResourceType === 'item_set') {
            [$resourceToDisplay, $itemSetIdForLink, $relatedItems, $media] =
                $this->loadItemSetBranch($requestedId);
        } else {
            [$resourceToDisplay, $itemSetIdForLink, $media] = $this->loadItemBranch($requestedId);
        }

        if (!$resourceToDisplay) {
            throw new \Exception('Resource could not be loaded.');
        }

        $properties = $this->buildSortedPropertyRows($resourceToDisplay);

        return new ResourceDetailPresentationData(
            $resourceToDisplay,
            $requestedResourceType,
            $itemSetIdForLink,
            $properties,
            $relatedItems,
            $media
        );
    }

    /**
     * @return array{0: mixed, 1: int|null, 2: array, 3: array}
     */
    private function loadItemSetBranch(int $requestedId): array
    {
        $itemSetIdForLink = $requestedId;

        $itemSetResource = $this->api->read('item_sets', $requestedId)->getContent();
        if (!$itemSetResource) {
            throw new \Exception("Item Set with ID {$requestedId} not found.");
        }

        $resourceToDisplay = $itemSetResource;

        $excavationItems = $this->api->search('items', [
            'item_set_id' => $requestedId,
            'sort_by' => 'created',
            'sort_order' => 'asc',
            'limit' => 10,
        ])->getContent();

        foreach ($excavationItems as $item) {
            $resourceClass = $item->resourceClass();
            if ($resourceClass && (
                stripos($resourceClass->label(), 'excavation') !== false
                || stripos($item->displayTitle(), 'excavation') !== false
            )) {
                $resourceToDisplay = $item;
                break;
            }
        }

        if ($resourceToDisplay->resourceName() !== 'items' && !empty($excavationItems)) {
            $resourceToDisplay = $excavationItems[0];
        }

        $searchParams1 = [
            'item_set_id' => $requestedId,
            'sort_by' => 'created',
            'sort_order' => 'desc',
            'per_page' => 1000,
        ];

        $response1 = $this->api->search('items', $searchParams1);
        $relatedItems = $response1->getContent();

        if (empty($relatedItems)) {
            $allItemsResponse = $this->api->search('items', ['per_page' => 1000]);
            $allItems = $allItemsResponse->getContent();

            foreach ($allItems as $item) {
                $itemSets = $item->itemSets();
                foreach ($itemSets as $itemSet) {
                    if ($itemSet->id() == $requestedId) {
                        $relatedItems[] = $item;
                    }
                }
            }
        }

        $media = $this->collectImageMediaFromItems($relatedItems);

        return [$resourceToDisplay, $itemSetIdForLink, $relatedItems, $media];
    }

    /**
     * @return array{0: mixed, 1: int|null, 2: array}
     */
    private function loadItemBranch(int $requestedId): array
    {
        $relatedItems = [];
        $resourceToDisplay = $this->api->read('items', $requestedId)->getContent();

        if (!$resourceToDisplay) {
            throw new \Exception("Item with ID {$requestedId} not found.");
        }

        $itemSetIdForLink = null;
        $itemSets = $resourceToDisplay->itemSets();
        if (!empty($itemSets) && isset($itemSets[0]) && is_object($itemSets[0])) {
            $itemSetIdForLink = $itemSets[0]->id();
        }

        $media = [];
        foreach ($resourceToDisplay->media() as $m) {
            if (strpos($m->mediaType(), 'image/') === 0) {
                $media[] = $m;
            }
        }

        return [$resourceToDisplay, $itemSetIdForLink, $media];
    }

    /**
     * @param object $resourceToDisplay
     *
     * @return list<array{term: mixed, label: string, values: mixed}>
     */
    private function buildSortedPropertyRows($resourceToDisplay): array
    {
        $properties = [];
        $values = $resourceToDisplay->values();

        foreach ($values as $term => $propertyData) {
            try {
                if (empty($propertyData)) {
                    continue;
                }

                $propertyLabel = $this->vocabularyLabelService->getHumanReadableLabel($term);
                $propertyValues = [];

                if (is_array($propertyData)) {
                    if (isset($propertyData['values']) && is_array($propertyData['values'])) {
                        $propertyValues = $propertyData['values'];
                        if (isset($propertyData['property']) && is_object($propertyData['property'])) {
                            if (method_exists($propertyData['property'], 'label')) {
                                $propertyLabel = $propertyData['property']->label();
                            }
                        }
                    } else {
                        foreach ($propertyData as $item) {
                            if (is_object($item) && method_exists($item, 'value')) {
                                $propertyValues[] = $item;
                                if (count($propertyValues) === 1 && method_exists($item, 'property')) {
                                    $prop = $item->property();
                                    if ($prop && method_exists($prop, 'label')) {
                                        $propertyLabel = $prop->label();
                                    }
                                }
                            }
                        }
                    }
                } elseif (is_object($propertyData) && method_exists($propertyData, 'value')) {
                    $propertyValues = [$propertyData];
                    if (method_exists($propertyData, 'property')) {
                        $prop = $propertyData->property();
                        if ($prop && method_exists($prop, 'label')) {
                            $propertyLabel = $prop->label();
                        }
                    }
                }

                if (!empty($propertyValues)) {
                    $properties[] = [
                        'term' => $term,
                        'label' => $propertyLabel,
                        'values' => $propertyValues,
                    ];
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        usort($properties, static function ($a, $b) {
            if ($a['label'] === 'Title') {
                return -1;
            }
            if ($b['label'] === 'Title') {
                return 1;
            }

            return strcmp($a['label'], $b['label']);
        });

        return $properties;
    }

    /**
     * @param iterable<object> $items Omeka item representations
     *
     * @return list<mixed> Media representations whose MIME type starts with image/
     */
    private function collectImageMediaFromItems(iterable $items): array
    {
        $media = [];
        foreach ($items as $item) {
            foreach ($item->media() as $m) {
                if (strpos($m->mediaType(), 'image/') === 0) {
                    $media[] = $m;
                }
            }
        }

        return $media;
    }
}
