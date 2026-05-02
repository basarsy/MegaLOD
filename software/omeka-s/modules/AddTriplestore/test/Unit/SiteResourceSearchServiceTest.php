<?php

declare(strict_types=1);

namespace AddTriplestore\Test\Unit;

use AddTriplestore\Service\Site\SiteResourceSearchService;
use AddTriplestore\Service\Site\SiteSearchQuery;
use Laminas\Stdlib\Parameters;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Response as ApiResponse;
use PHPUnit\Framework\TestCase;

final class SiteResourceSearchServiceTest extends TestCase
{
    public function testSearchSkipsApiWhenNoQueryNorFilters(): void
    {
        $api = $this->createMock(ApiManager::class);
        $api->expects($this->never())->method('search');

        $svc = new SiteResourceSearchService($api);
        $q = SiteSearchQuery::fromParameters(new Parameters([]));
        $result = $svc->search($q);

        $this->assertSame([], $result->results);
        $this->assertSame(0, $result->totalItems);
        $this->assertSame(0, $result->totalItemSets);
    }

    public function testSearchInvokesItemsWhenTypeItemsAndQuerySet(): void
    {
        $response = new ApiResponse([]);
        $response->setTotalResults(0);

        $api = $this->createMock(ApiManager::class);
        $api->expects($this->once())
            ->method('search')
            ->with(
                $this->equalTo('items'),
                $this->callback(static function (array $params): bool {
                    return isset($params['fulltext_search']) && $params['fulltext_search'] === 'flint';
                })
            )
            ->willReturn($response);

        $svc = new SiteResourceSearchService($api);
        $q = SiteSearchQuery::fromParameters(new Parameters([
            'type' => 'items',
            'query' => 'flint',
        ]));
        $result = $svc->search($q);

        $this->assertSame(0, $result->totalItems);
    }
}
