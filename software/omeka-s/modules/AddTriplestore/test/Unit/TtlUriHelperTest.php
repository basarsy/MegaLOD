<?php

declare(strict_types=1);

namespace AddTriplestore\Test\Unit;

use AddTriplestore\Service\Ttl\TtlUriHelper;
use PHPUnit\Framework\TestCase;

final class TtlUriHelperTest extends TestCase
{
    public function testCreateUrlSlugNonEmptyDefaults(): void
    {
        $h = new TtlUriHelper();
        $this->assertSame('foo-bar', $h->createUrlSlug('Foo Bar'));
        $this->assertSame('unknown', $h->createUrlSlug('!!!'));
    }

    public function testSanitizeForUriStripsParensTail(): void
    {
        $h = new TtlUriHelper();
        $this->assertSame('A1', $h->sanitizeForUri('A1 (south)'));
    }
}
