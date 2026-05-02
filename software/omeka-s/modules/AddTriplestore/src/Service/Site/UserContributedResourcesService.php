<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Site;

use Omeka\Api\Manager as ApiManager;

/**
 * Loads item and item set lists for the site "my data" view (arrowhead-filtered items).
 */
final class UserContributedResourcesService
{
    private const LIST_LIMIT = 50;

    /** @var ApiManager */
    private $api;

    /** @var ArrowheadItemClassifier */
    private $arrowheadItemClassifier;

    public function __construct(ApiManager $api, ArrowheadItemClassifier $arrowheadItemClassifier)
    {
        $this->api = $api;
        $this->arrowheadItemClassifier = $arrowheadItemClassifier;
    }

    /**
     * @return array{items: array, item_sets: array}
     */
    public function getMyArrowheadListsForOwner(int $userId): array
    {
        $items = [];
        $itemSets = [];

        $response = $this->api->search('items', [
            'owner_id' => $userId,
            'sort_by' => 'created',
            'sort_order' => 'desc',
            'limit' => self::LIST_LIMIT,
        ]);
        $items = $response->getContent();

        $itemSetResponse = $this->api->search('item_sets', [
            'owner_id' => $userId,
            'sort_by' => 'created',
            'sort_order' => 'desc',
            'limit' => self::LIST_LIMIT,
        ]);
        $itemSets = $itemSetResponse->getContent();

        foreach ($itemSets as $itemSet) {
            $itemSetItems = $this->api->search('items', [
                'item_set_id' => $itemSet->id(),
                'limit' => self::LIST_LIMIT,
            ])->getContent();

            foreach ($itemSetItems as $item) {
                $found = false;
                foreach ($items as $existingItem) {
                    if ($existingItem->id() == $item->id()) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $items[] = $item;
                }
            }
        }

        $filteredItems = [];
        foreach ($items as $item) {
            if ($this->arrowheadItemClassifier->isMyDataArrowheadItem($item)) {
                $filteredItems[] = $item;
            }
        }

        return [
            'items' => $filteredItems,
            'item_sets' => $itemSets,
        ];
    }
}
