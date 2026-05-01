<?php

declare(strict_types=1);

namespace AddTriplestore\Test\Unit;

use AddTriplestore\Service\Ttl\VocabularyLabelService;
use PHPUnit\Framework\TestCase;

final class VocabularyLabelServiceTest extends TestCase
{
    public function testKnownTermReturnsMappedLabel(): void
    {
        $svc = new VocabularyLabelService();
        $this->assertSame('Title', $svc->getHumanReadableLabel('dcterms:title'));
    }

    public function testUnknownCamelCaseTermIsSplit(): void
    {
        $svc = new VocabularyLabelService();
        $this->assertStringContainsString('Foo', $svc->getHumanReadableLabel('ex:fooBarBaz'));
    }
}
