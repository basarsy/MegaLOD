<?php

declare(strict_types=1);

namespace AddTriplestore\Test\Unit;

use AddTriplestore\Service\Site\SiteSearchQuery;
use Laminas\Stdlib\Parameters;
use PHPUnit\Framework\TestCase;

final class SiteSearchQueryTest extends TestCase
{
    public function testHasSearchOrFiltersFalseWhenEmpty(): void
    {
        $q = SiteSearchQuery::fromParameters(new Parameters([]));
        $this->assertFalse($q->hasSearchOrFilters());
    }

    public function testHasSearchOrFiltersTrueForQuery(): void
    {
        $q = SiteSearchQuery::fromParameters(new Parameters(['query' => 'test']));
        $this->assertTrue($q->hasSearchOrFilters());
    }

    public function testHasFiltersTrueForShape(): void
    {
        $q = SiteSearchQuery::fromParameters(new Parameters(['shape' => 'triangular']));
        $this->assertTrue($q->hasFilters());
        $this->assertTrue($q->hasSearchOrFilters());
    }
}
