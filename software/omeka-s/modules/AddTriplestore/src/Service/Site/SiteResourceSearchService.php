<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Site;

use Omeka\Api\Manager as ApiManager;

/**
 * Omeka API search orchestration for the site search page.
 */
final class SiteResourceSearchService
{
    /** @var ApiManager */
    private $api;

    public function __construct(ApiManager $api)
    {
        $this->api = $api;
    }

    public function search(SiteSearchQuery $query): SiteResourceSearchResult
    {
        $results = [];
        $totalItems = 0;
        $totalItemSets = 0;

        if (!$query->hasSearchOrFilters()) {
            return new SiteResourceSearchResult($results, $totalItems, $totalItemSets);
        }

        $searchQuery = $query->searchQuery;
        $searchType = $query->searchType;
        $filterArchaeologist = $query->filterArchaeologist;
        $filterOrcid = $query->filterOrcid;
        $filterCountry = $query->filterCountry;
        $filterDistrict = $query->filterDistrict;
        $filterParish = $query->filterParish;
        $filterShape = $query->filterShape;
        $filterVariant = $query->filterVariant;
        $filterMaterial = $query->filterMaterial;
        $filterElongation = $query->filterElongation;
        $filterThickness = $query->filterThickness;
        $filterBase = $query->filterBase;
        $filterCondition = $query->filterCondition;
        $filterChippingMode = $query->filterChippingMode;
        $filterChippingDirection = $query->filterChippingDirection;
        $filterChippingDelineation = $query->filterChippingDelineation;
        $filterChippingShape = $query->filterChippingShape;
        $filterChippingAmplitude = $query->filterChippingAmplitude;
        $minHeight = $query->minHeight;
        $maxHeight = $query->maxHeight;
        $minWidth = $query->minWidth;
        $maxWidth = $query->maxWidth;
        $minThickness = $query->minThickness;
        $maxThickness = $query->maxThickness;
        $minWeight = $query->minWeight;
        $maxWeight = $query->maxWeight;

        if ($searchType === 'all' || $searchType === 'item_sets') {
            $itemSetQuery = [];

            if ($searchQuery) {
                $itemSetQuery['fulltext_search'] = $searchQuery;
            }

            if ($filterArchaeologist || $filterOrcid || $filterCountry || $filterDistrict || $filterParish) {
                $excavationItemQuery = [];
                $propertyFilters = [];

                if ($filterArchaeologist) {
                    $propertyFilters[] = [
                        'property' => 7665,
                        'type' => 'in',
                        'text' => $filterArchaeologist,
                    ];
                }

                if ($filterOrcid) {
                    $propertyFilters[] = [
                        'property' => 176,
                        'type' => 'eq',
                        'text' => $filterOrcid,
                    ];
                }

                if ($filterCountry) {
                    $propertyFilters[] = [
                        'property' => 1402,
                        'type' => 'eq',
                        'text' => $filterCountry,
                    ];
                }

                if ($filterDistrict) {
                    $propertyFilters[] = [
                        'property' => 1555,
                        'type' => 'eq',
                        'text' => $filterDistrict,
                    ];
                }

                if ($filterParish) {
                    $propertyFilters[] = [
                        'property' => 1681,
                        'type' => 'eq',
                        'text' => $filterParish,
                    ];
                }

                if (!empty($propertyFilters)) {
                    $excavationItemQuery['property'] = $propertyFilters;
                }

                $excavationItemQuery['fulltext_search'] = 'Excavation';

                $excavationItemsResponse = $this->api->search('items', $excavationItemQuery);
                $excavationItems = $excavationItemsResponse->getContent();

                $itemSetIds = [];
                foreach ($excavationItems as $item) {
                    $itemSets = $item->itemSets();
                    foreach ($itemSets as $itemSet) {
                        $itemSetIds[] = $itemSet->id();
                    }
                }

                $itemSetIds = array_unique($itemSetIds);

                if (!empty($itemSetIds)) {
                    $itemSetQuery['id'] = $itemSetIds;

                    if ($searchQuery) {
                        unset($itemSetQuery['fulltext_search']);
                    }

                    $itemSetsResponse = $this->api->search('item_sets', $itemSetQuery);
                    $results['item_sets'] = $itemSetsResponse->getContent();
                    $totalItemSets = $itemSetsResponse->getTotalResults();
                } else {
                    $results['item_sets'] = [];
                    $totalItemSets = 0;
                }
            } else {
                $itemSetsResponse = $this->api->search('item_sets', $itemSetQuery);
                $results['item_sets'] = $itemSetsResponse->getContent();
                $totalItemSets = $itemSetsResponse->getTotalResults();
            }
        }

        if ($searchType === 'all' || $searchType === 'items') {
            $itemQuery = [];

            if ($searchQuery) {
                if ($searchQuery === 'arrowhead') {
                    $itemQuery['fulltext_search'] = 'arrowhead* OR "archaeological item*"';
                } else {
                    $itemQuery['fulltext_search'] = $searchQuery;
                }
            }

            $propertyFilters = [];

            if ($filterShape) {
                $propertyFilters[] = [
                    'property' => 7651,
                    'type' => 'eq',
                    'text' => $filterShape,
                ];
            }

            if ($filterVariant) {
                $propertyFilters[] = [
                    'property' => 7652,
                    'type' => 'eq',
                    'text' => $filterVariant,
                ];
            }

            if ($filterMaterial) {
                $propertyFilters[] = [
                    'property' => 4633,
                    'type' => 'eq',
                    'text' => $filterMaterial,
                ];
            }

            if ($filterElongation) {
                $propertyFilters[] = [
                    'property' => 7676,
                    'type' => 'eq',
                    'text' => $filterElongation,
                ];
            }

            if ($filterThickness) {
                $propertyFilters[] = [
                    'property' => 7677,
                    'type' => 'eq',
                    'text' => $filterThickness,
                ];
            }

            if ($filterBase) {
                $propertyFilters[] = [
                    'property' => 7653,
                    'type' => 'eq',
                    'text' => $filterBase,
                ];
            }

            if ($filterCondition !== '') {
                $propertyFilters[] = [
                    'property' => 476,
                    'type' => 'eq',
                    'text' => $filterCondition,
                ];
            }

            if ($filterChippingMode) {
                $propertyFilters[] = [
                    'property' => 7656,
                    'type' => 'eq',
                    'text' => $filterChippingMode,
                ];
            }

            if ($filterChippingDirection) {
                $propertyFilters[] = [
                    'property' => 7658,
                    'type' => 'eq',
                    'text' => $filterChippingDirection,
                ];
            }

            if ($filterChippingDelineation) {
                $propertyFilters[] = [
                    'property' => 7660,
                    'type' => 'eq',
                    'text' => $filterChippingDelineation,
                ];
            }

            if ($filterChippingShape) {
                $propertyFilters[] = [
                    'property' => 7661,
                    'type' => 'eq',
                    'text' => $filterChippingShape,
                ];
            }

            if ($filterChippingAmplitude !== '') {
                $propertyFilters[] = [
                    'property' => 7657,
                    'type' => 'eq',
                    'text' => $filterChippingAmplitude,
                ];
            }

            if ($minHeight) {
                $propertyFilters[] = [
                    'property' => 5616,
                    'type' => 'gte',
                    'text' => $minHeight,
                ];
            }

            if ($maxHeight) {
                $propertyFilters[] = [
                    'property' => 5616,
                    'type' => 'lte',
                    'text' => $maxHeight,
                ];
            }

            if ($minWidth) {
                $propertyFilters[] = [
                    'property' => 5688,
                    'type' => 'gte',
                    'text' => $minWidth,
                ];
            }

            if ($maxWidth) {
                $propertyFilters[] = [
                    'property' => 5688,
                    'type' => 'lte',
                    'text' => $maxWidth,
                ];
            }

            if ($minThickness) {
                $propertyFilters[] = [
                    'property' => 7244,
                    'type' => 'gte',
                    'text' => $minThickness,
                ];
            }

            if ($maxThickness) {
                $propertyFilters[] = [
                    'property' => 7244,
                    'type' => 'lte',
                    'text' => $maxThickness,
                ];
            }

            if ($minWeight) {
                $propertyFilters[] = [
                    'property' => 5779,
                    'type' => 'gte',
                    'text' => $minWeight,
                ];
            }

            if ($maxWeight) {
                $propertyFilters[] = [
                    'property' => 5779,
                    'type' => 'lte',
                    'text' => $maxWeight,
                ];
            }

            if ($propertyFilters !== []) {
                $itemQuery['property'] = $propertyFilters;
            }

            $itemsResponse = $this->api->search('items', $itemQuery);
            $results['items'] = $itemsResponse->getContent();
            $totalItems = $itemsResponse->getTotalResults();
        }

        return new SiteResourceSearchResult($results, $totalItems, $totalItemSets);
    }
}
