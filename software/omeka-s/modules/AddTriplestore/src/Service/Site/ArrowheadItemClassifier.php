<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Site;

/**
 * Centralizes arrowhead vs non-artifact heuristics for site listing and TTL export paths.
 */
final class ArrowheadItemClassifier
{
    /** @var list<string> */
    private const ARROWHEAD_PROPERTY_LABELS = [
        'Arrowhead Shape', 'Arrowhead Variant', 'Arrowhead Base',
        'Chipping Mode', 'Chipping Direction', 'Chipping Shape',
    ];

    /** @var list<string> */
    private const NON_ARROWHEAD_TITLE_PATTERNS = [
        '/^context/i', '/^ctx-/i', '/^square/i',
        '/^svu/i', '/^layer-/i', '/^stratigraphic/i',
        '/^excav/i', '/^excavation/i', '/^location/i',
        '/^archaeological encounter/i',
    ];

    /**
     * Strict match: resource class label or characteristic properties (e.g. download TTL branch).
     *
     * @param object $item Omeka item representation
     */
    public function isLikelyArrowheadResource($item): bool
    {
        $resourceClass = $item->resourceClass();
        if ($resourceClass && strpos(strtolower((string) $resourceClass->label()), 'arrowhead') !== false) {
            return true;
        }

        $values = $item->values();
        foreach (self::ARROWHEAD_PROPERTY_LABELS as $property) {
            if (isset($values[$property]) && !empty($values[$property])) {
                return true;
            }
        }

        return false;
    }

    /**
     * "My data" list: expands on {@see isLikelyArrowheadResource} with title heuristics and exclusions.
     *
     * @param object $item Omeka item representation
     */
    public function isMyDataArrowheadItem($item): bool
    {
        $isArrowhead = $this->isLikelyArrowheadResource($item);

        if (!$isArrowhead) {
            $title = $item->displayTitle();
            if (strpos(strtolower($title), 'arrowhead') !== false
                || strpos($title, 'AH-') === 0
                || preg_match('/^(?:item|archaeological item)\s+AH-/i', $title) !== 0) {
                $isArrowhead = true;
            }
        }

        if (!$isArrowhead) {
            $title = $item->displayTitle();
            $isNonArrowhead = false;
            foreach (self::NON_ARROWHEAD_TITLE_PATTERNS as $pattern) {
                if (preg_match($pattern, $title) !== 0) {
                    $isNonArrowhead = true;
                    break;
                }
            }
            $isArrowhead = !$isNonArrowhead;
        }

        return $isArrowhead;
    }
}
