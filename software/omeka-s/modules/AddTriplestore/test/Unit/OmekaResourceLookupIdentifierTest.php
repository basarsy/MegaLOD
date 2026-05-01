<?php

declare(strict_types=1);

namespace AddTriplestore\Test\Unit;

use AddTriplestore\Service\Ingestion\OmekaResourceLookupService;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Response;
use PHPUnit\Framework\TestCase;

final class OmekaResourceLookupIdentifierTest extends TestCase
{
    public function testItemExistsWithDctermsIdentifierChecksSearch(): void
    {
        $response = $this->createMock(Response::class);
        $response->method('getTotalResults')->willReturn(1);

        $api = $this->createMock(ApiManager::class);
        $api->expects($this->once())
            ->method('search')
            ->with(
                $this->equalTo('items'),
                $this->callback(static function (array $params): bool {
                    return ($params['limit'] ?? null) === 1
                        && (($params['property'][0]['text']) ?? '') === 'EX-42';
                })
            )
            ->willReturn($response);

        $svc = new OmekaResourceLookupService($api);
        $this->assertTrue($svc->itemExistsWithDctermsIdentifier('EX-42'));
    }

    public function testEmptyIdentifierIsFalse(): void
    {
        $api = $this->createMock(ApiManager::class);
        $api->expects($this->never())->method('search');
        $svc = new OmekaResourceLookupService($api);
        $this->assertFalse($svc->itemExistsWithDctermsIdentifier(''));
    }
}
