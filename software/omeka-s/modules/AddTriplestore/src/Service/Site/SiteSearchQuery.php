<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Site;

use Laminas\Stdlib\Parameters;

/**
 * HTTP query parameter object for site resource search (items + item sets).
 */
final class SiteSearchQuery
{
    /** @var string */
    public $searchQuery;

    /** @var string */
    public $searchType;

    /** @var int */
    public $page;

    /** @var int */
    public $perPage;

    /** @var string */
    public $filterArchaeologist;

    /** @var string */
    public $filterOrcid;

    /** @var string */
    public $filterCountry;

    /** @var string */
    public $filterDistrict;

    /** @var string */
    public $filterParish;

    /** @var string */
    public $filterShape;

    /** @var string */
    public $filterVariant;

    /** @var string */
    public $filterMaterial;

    /** @var string */
    public $filterElongation;

    /** @var string */
    public $filterThickness;

    /** @var string */
    public $filterBase;

    /** @var string */
    public $filterCondition;

    /** @var string */
    public $filterChippingMode;

    /** @var string */
    public $filterChippingDirection;

    /** @var string */
    public $filterChippingDelineation;

    /** @var string */
    public $filterChippingShape;

    /** @var string */
    public $filterChippingAmplitude;

    /** @var string */
    public $minHeight;

    /** @var string */
    public $maxHeight;

    /** @var string */
    public $minWidth;

    /** @var string */
    public $maxWidth;

    /** @var string */
    public $minThickness;

    /** @var string */
    public $maxThickness;

    /** @var string */
    public $minWeight;

    /** @var string */
    public $maxWeight;

    public static function fromParameters(Parameters $q): self
    {
        $o = new self();
        $o->searchQuery = (string) $q->get('query', '');
        $o->searchType = (string) $q->get('type', 'all');
        $o->page = (int) $q->get('page', 1);
        $o->perPage = 20;
        $o->filterArchaeologist = (string) $q->get('archaeologist', '');
        $o->filterOrcid = (string) $q->get('orcid', '');
        $o->filterCountry = (string) $q->get('country', '');
        $o->filterDistrict = (string) $q->get('district', '');
        $o->filterParish = (string) $q->get('parish', '');
        $o->filterShape = (string) $q->get('shape', '');
        $o->filterVariant = (string) $q->get('variant', '');
        $o->filterMaterial = (string) $q->get('material', '');
        $o->filterElongation = (string) $q->get('elongation', '');
        $o->filterThickness = (string) $q->get('thickness', '');
        $o->filterBase = (string) $q->get('base', '');
        $o->filterCondition = (string) $q->get('condition', '');
        $o->filterChippingMode = (string) $q->get('chippingMode', '');
        $o->filterChippingDirection = (string) $q->get('chippingDirection', '');
        $o->filterChippingDelineation = (string) $q->get('chippingDelineation', '');
        $o->filterChippingShape = (string) $q->get('chippingShape', '');
        $o->filterChippingAmplitude = (string) $q->get('chippingAmplitude', '');
        $o->minHeight = (string) $q->get('minHeight', '');
        $o->maxHeight = (string) $q->get('maxHeight', '');
        $o->minWidth = (string) $q->get('minWidth', '');
        $o->maxWidth = (string) $q->get('maxWidth', '');
        $o->minThickness = (string) $q->get('minThickness', '');
        $o->maxThickness = (string) $q->get('maxThickness', '');
        $o->minWeight = (string) $q->get('minWeight', '');
        $o->maxWeight = (string) $q->get('maxWeight', '');

        return $o;
    }

    public function hasFilters(): bool
    {
        return (bool) ($this->filterShape || $this->filterVariant || $this->filterMaterial || $this->filterElongation
            || $this->filterThickness || $this->filterBase || $this->filterCondition || $this->filterChippingMode
            || $this->filterChippingDirection || $this->filterChippingDelineation || $this->filterChippingShape
            || $this->filterChippingAmplitude || $this->minHeight || $this->maxHeight || $this->minWidth || $this->maxWidth
            || $this->minThickness || $this->maxThickness || $this->minWeight || $this->maxWeight
            || $this->filterArchaeologist || $this->filterOrcid || $this->filterCountry || $this->filterDistrict
            || $this->filterParish);
    }

    public function hasSearchOrFilters(): bool
    {
        return $this->searchQuery !== '' || $this->hasFilters();
    }
}
