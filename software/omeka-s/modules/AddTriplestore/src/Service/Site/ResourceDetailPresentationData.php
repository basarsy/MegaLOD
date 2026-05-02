<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Site;

/**
 * Read model for site resource detail view (item or item_set).
 *
 * @phpstan-type PropertyRow array{term: mixed, label: string, values: mixed}
 */
final class ResourceDetailPresentationData
{
    /** @var mixed */
    public $resource;

    /** @var string */
    public $requestedResourceType;

    /** @var int|null */
    public $itemSetIdForLink;

    /** @var list<PropertyRow> */
    public $properties;

    /** @var array */
    public $relatedItems;

    /** @var array */
    public $media;

    /**
     * @param list<PropertyRow> $properties
     * @param array             $relatedItems
     * @param array             $media
     */
    public function __construct(
        $resource,
        string $requestedResourceType,
        ?int $itemSetIdForLink,
        array $properties,
        array $relatedItems,
        array $media
    ) {
        $this->resource = $resource;
        $this->requestedResourceType = $requestedResourceType;
        $this->itemSetIdForLink = $itemSetIdForLink;
        $this->properties = $properties;
        $this->relatedItems = $relatedItems;
        $this->media = $media;
    }
}
