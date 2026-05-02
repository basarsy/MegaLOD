<?php

declare(strict_types=1);

namespace AddTriplestore\Test\Unit;

use AddTriplestore\Service\Site\UploadRequestContext;
use Laminas\Stdlib\Parameters;
use PHPUnit\Framework\TestCase;

final class UploadRequestContextTest extends TestCase
{
    public function testDefaultsModeUpload(): void
    {
        $ctx = UploadRequestContext::fromRequestParameters(new Parameters([]), new Parameters([]));
        $this->assertSame('upload', $ctx->mode);
        $this->assertNull($ctx->uploadType);
    }

    public function testQueryOverridesPostForUploadType(): void
    {
        $ctx = UploadRequestContext::fromRequestParameters(
            new Parameters(['upload_type' => 'arrowhead']),
            new Parameters(['upload_type' => 'excavation'])
        );
        $this->assertSame('arrowhead', $ctx->uploadType);
    }

    public function testGetItemSetIdAsIntParsesNumericString(): void
    {
        $ctx = UploadRequestContext::fromRequestParameters(
            new Parameters(['item_set_id' => '42']),
            new Parameters([])
        );
        $this->assertSame(42, $ctx->getItemSetIdAsInt());
    }

    public function testGetItemSetIdAsIntReturnsNullForNonNumeric(): void
    {
        $ctx = UploadRequestContext::fromRequestParameters(
            new Parameters(['item_set_id' => 'not-a-number']),
            new Parameters([])
        );
        $this->assertNull($ctx->getItemSetIdAsInt());
    }
}
