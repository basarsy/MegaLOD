<?php

declare(strict_types=1);

namespace AddTriplestore\Test\Unit;

use AddTriplestore\Service\Site\ArrowheadItemClassifier;
use PHPUnit\Framework\TestCase;

final class ArrowheadItemClassifierTest extends TestCase
{
    public function testLikelyArrowheadTrueWhenResourceClassLabelContainsArrowhead(): void
    {
        $item = new class {
            public function resourceClass()
            {
                return new class {
                    public function label(): string
                    {
                        return 'Arrowhead Type';
                    }
                };
            }

            public function values(): array
            {
                return [];
            }

            public function displayTitle(): string
            {
                return 'Something';
            }
        };
        $this->assertTrue((new ArrowheadItemClassifier())->isLikelyArrowheadResource($item));
        $this->assertTrue((new ArrowheadItemClassifier())->isMyDataArrowheadItem($item));
    }

    public function testLikelyArrowheadTrueWhenPropertyPresent(): void
    {
        $item = new class {
            public function resourceClass()
            {
                return null;
            }

            public function values(): array
            {
                return ['Arrowhead Shape' => ['x']];
            }

            public function displayTitle(): string
            {
                return 'Plain title';
            }
        };
        $this->assertTrue((new ArrowheadItemClassifier())->isLikelyArrowheadResource($item));
    }

    public function testLikelyArrowheadFalseWhenNoSignals(): void
    {
        $item = new class {
            public function resourceClass()
            {
                return null;
            }

            public function values(): array
            {
                return [];
            }

            public function displayTitle(): string
            {
                return 'Random artifact';
            }
        };
        $this->assertFalse((new ArrowheadItemClassifier())->isLikelyArrowheadResource($item));
    }

    public function testMyDataExcludesExcavationTitlePattern(): void
    {
        $item = new class {
            public function resourceClass()
            {
                return null;
            }

            public function values(): array
            {
                return [];
            }

            public function displayTitle(): string
            {
                return 'Excavation test site';
            }
        };
        $this->assertFalse((new ArrowheadItemClassifier())->isMyDataArrowheadItem($item));
    }

    public function testMyDataIncludesAhPrefixTitle(): void
    {
        $item = new class {
            public function resourceClass()
            {
                return null;
            }

            public function values(): array
            {
                return [];
            }

            public function displayTitle(): string
            {
                return 'AH-12345 piece';
            }
        };
        $this->assertTrue((new ArrowheadItemClassifier())->isMyDataArrowheadItem($item));
    }
}
