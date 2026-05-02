<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Site;

/**
 * @phpstan-type SearchResults array<string, mixed>
 */
final class SiteResourceSearchResult
{
    /** @var SearchResults */
    public $results;

    /** @var int */
    public $totalItems;

    /** @var int */
    public $totalItemSets;

    /** @param SearchResults $results */
    public function __construct(array $results, int $totalItems, int $totalItemSets)
    {
        $this->results = $results;
        $this->totalItems = $totalItems;
        $this->totalItemSets = $totalItemSets;
    }

    public function getTotalResults(): int
    {
        return $this->totalItems + $this->totalItemSets;
    }
}
