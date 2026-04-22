<?php

namespace AddTriplestore\Service\Ttl;

use AddTriplestore\Service\MegalodConfig;

/**
 * Rewrites canonical public-base URIs in Turtle to local item-set-scoped URIs.
 */
final class TtlUriNormalizer
{
    /** @var MegalodConfig */
    private $megalodConfig;

    public function __construct(MegalodConfig $megalodConfig)
    {
        $this->megalodConfig = $megalodConfig;
    }

    /**
     * @param mixed $itemSetId
     * @param callable $resolveExcavationId function ($setId): ?string — e.g. site mapping / Omeka lookup
     */
    public function normalizeUris(string $ttlData, $itemSetId, callable $resolveExcavationId): string
    {
        if (!$itemSetId) {
            return $ttlData;
        }

        $excavationIdentifier = $resolveExcavationId($itemSetId);
        if ($excavationIdentifier === null) {
            if (preg_match('/dct:identifier\s+"([^"]+)"/i', $ttlData, $matches)) {
                $excavationIdentifier = $matches[1];
            } else {
                $excavationIdentifier = 'excavation-' . $itemSetId;
            }
        }

        $modifiedTtl = $ttlData;
        $replacements = 0;
        $baseDataGraphUri = $this->megalodConfig->getMegalodPublicBaseUri();
        $localBaseUri = $this->megalodConfig->getMegalodLocalBaseUri();
        $escapedBase = preg_quote($baseDataGraphUri, '/');

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . 'excavation\/([^>]+)>/',
            function ($matches) use ($itemSetId, $excavationIdentifier, &$replacements, $localBaseUri) {
                if (strpos($matches[0], '/kos/') !== false) {
                    return $matches[0];
                }
                $replacements++;

                return '<' . $localBaseUri . $itemSetId . '/excavation/' . $excavationIdentifier . '>';
            },
            $modifiedTtl
        );

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . '([^\/]+\/)?location\/([^>]+)>/',
            function ($matches) use ($itemSetId, $excavationIdentifier, &$replacements, $localBaseUri) {
                if (strpos($matches[0], '/kos/') !== false) {
                    return $matches[0];
                }
                $locationId = $matches[2];
                $replacements++;

                return '<' . $localBaseUri . $itemSetId . '/excavation/' . $excavationIdentifier . '/location/' . $locationId . '>';
            },
            $modifiedTtl
        );

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . 'gps\/([^>]+)>/',
            function ($matches) use ($itemSetId, $excavationIdentifier, &$replacements, $localBaseUri) {
                if (strpos($matches[0], '/kos/') !== false) {
                    return $matches[0];
                }
                $gpsId = $matches[1];
                $replacements++;

                return '<' . $localBaseUri . $itemSetId . '/excavation/' . $excavationIdentifier . '/gps/' . $gpsId . '>';
            },
            $modifiedTtl
        );

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . 'archaeologist\/([^>]+)>/',
            function ($matches) use ($itemSetId, $excavationIdentifier, &$replacements, $localBaseUri) {
                if (strpos($matches[0], '/kos/') !== false) {
                    return $matches[0];
                }
                $archaeologistId = $matches[1];
                $replacements++;

                return '<' . $localBaseUri . $itemSetId . '/excavation/' . $excavationIdentifier . '/archaeologist/' . $archaeologistId . '>';
            },
            $modifiedTtl
        );

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . 'square\/([^>]+)>/',
            function ($matches) use ($itemSetId, $excavationIdentifier, &$replacements, $localBaseUri) {
                if (strpos($matches[0], '/kos/') !== false) {
                    return $matches[0];
                }
                $squareId = $matches[1];
                $replacements++;

                return '<' . $localBaseUri . $itemSetId . '/excavation/' . $excavationIdentifier . '/square/' . $squareId . '>';
            },
            $modifiedTtl
        );

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . 'context\/([^>]+)>/',
            function ($matches) use ($itemSetId, $excavationIdentifier, &$replacements, $localBaseUri) {
                if (strpos($matches[0], '/kos/') !== false) {
                    return $matches[0];
                }
                $contextId = $matches[1];
                $replacements++;

                return '<' . $localBaseUri . $itemSetId . '/excavation/' . $excavationIdentifier . '/context/' . $contextId . '>';
            },
            $modifiedTtl
        );

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . 'svu\/([^>]+)>/',
            function ($matches) use ($itemSetId, $excavationIdentifier, &$replacements, $localBaseUri) {
                if (strpos($matches[0], '/kos/') !== false) {
                    return $matches[0];
                }
                $svuId = $matches[1];
                $replacements++;

                return '<' . $localBaseUri . $itemSetId . '/excavation/' . $excavationIdentifier . '/svu/' . $svuId . '>';
            },
            $modifiedTtl
        );

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . 'timeline\/([^>]+)>/',
            function ($matches) use ($itemSetId, $excavationIdentifier, &$replacements, $localBaseUri) {
                if (strpos($matches[0], '/kos/') !== false) {
                    return $matches[0];
                }
                $timelineId = $matches[1];
                $replacements++;

                return '<' . $localBaseUri . $itemSetId . '/excavation/' . $excavationIdentifier . '/timeline/' . $timelineId . '>';
            },
            $modifiedTtl
        );

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . 'instant\/([^>]+)>/',
            function ($matches) use ($itemSetId, $excavationIdentifier, &$replacements, $localBaseUri) {
                if (strpos($matches[0], '/kos/') !== false) {
                    return $matches[0];
                }
                $instantId = $matches[1];
                $replacements++;

                return '<' . $localBaseUri . $itemSetId . '/excavation/' . $excavationIdentifier . '/instant/' . $instantId . '>';
            },
            $modifiedTtl
        );

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . '[^>]*\/kos\/([^>]+)>/',
            function ($matches) use ($baseDataGraphUri) {
                return '<' . $baseDataGraphUri . 'kos/' . $matches[1] . '>';
            },
            $modifiedTtl
        );

        $itemIdentifier = null;
        if (preg_match('/dct:identifier\s+"([^"]+)"/i', $modifiedTtl, $matches)) {
            $itemIdentifier = $matches[1];
        } else {
            $itemIdentifier = 'item-' . $itemSetId;
        }

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . 'item\/([^>]+)>/',
            function ($matches) use ($itemSetId, $itemIdentifier, &$replacements, $localBaseUri) {
                $replacements++;

                return '<' . $localBaseUri . $itemSetId . '/item/' . $itemIdentifier . '>';
            },
            $modifiedTtl
        );

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . 'typometry\/([^>]+)>/',
            function ($matches) use ($itemSetId, $itemIdentifier, &$replacements, $localBaseUri) {
                $typometryId = $matches[1];
                $replacements++;

                return '<' . $localBaseUri . $itemSetId . '/item/' . $itemIdentifier . '/typometry/' . $typometryId . '>';
            },
            $modifiedTtl
        );

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . 'coordinates\/([^>]+)>/',
            function ($matches) use ($itemSetId, $itemIdentifier, &$replacements, $localBaseUri) {
                $coordinatesId = $matches[1];
                $replacements++;

                return '<' . $localBaseUri . $itemSetId . '/item/' . $itemIdentifier . '/coordinates/' . $coordinatesId . '>';
            },
            $modifiedTtl
        );

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . 'weight\/([^>]+)>/',
            function ($matches) use ($itemSetId, $itemIdentifier, &$replacements, $localBaseUri) {
                $weightId = $matches[1];
                $replacements++;

                return '<' . $localBaseUri . $itemSetId . '/item/' . $itemIdentifier . '/weight/' . $weightId . '>';
            },
            $modifiedTtl
        );

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . 'Morphology\/([^>]+)>/',
            function ($matches) use ($itemSetId, $itemIdentifier, &$replacements, $localBaseUri) {
                $morphologyId = $matches[1];
                $replacements++;

                return '<' . $localBaseUri . $itemSetId . '/item/' . $itemIdentifier . '/morphology/' . $morphologyId . '>';
            },
            $modifiedTtl
        );

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . 'BodyLength\/([^>]+)>/',
            function ($matches) use ($itemSetId, $itemIdentifier, &$replacements, $localBaseUri) {
                $bodyLengthId = $matches[1];
                $replacements++;

                return '<' . $localBaseUri . $itemSetId . '/item/' . $itemIdentifier . '/bodylength/' . $bodyLengthId . '>';
            },
            $modifiedTtl
        );

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . 'BaseLength\/([^>]+)>/',
            function ($matches) use ($itemSetId, $itemIdentifier, &$replacements, $localBaseUri) {
                $baseLengthId = $matches[1];
                $replacements++;

                return '<' . $localBaseUri . $itemSetId . '/item/' . $itemIdentifier . '/baselength/' . $baseLengthId . '>';
            },
            $modifiedTtl
        );

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . 'Chipping\/([^>]+)>/',
            function ($matches) use ($itemSetId, $itemIdentifier, &$replacements, $localBaseUri) {
                $chippingId = $matches[1];
                $replacements++;

                return '<' . $localBaseUri . $itemSetId . '/item/' . $itemIdentifier . '/chipping/' . $chippingId . '>';
            },
            $modifiedTtl
        );

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . 'gps\/([^>]+)>/',
            function ($matches) use ($itemSetId, $itemIdentifier, &$replacements, $localBaseUri) {
                $gpsId = $matches[1];
                $replacements++;

                return '<' . $localBaseUri . $itemSetId . '/item/' . $itemIdentifier . '/gps/' . $gpsId . '>';
            },
            $modifiedTtl
        );

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . 'encounter\/([^>]+)>/',
            function ($matches) use ($itemSetId, &$replacements, $localBaseUri, $resolveExcavationId) {
                $encounterId = $matches[1];
                $replacements++;

                $excId = $resolveExcavationId($itemSetId);
                $excavationIdentifier = $excId ?: 'excavation';

                return '<' . $localBaseUri . $itemSetId . '/excavation/' . $excavationIdentifier . '/encounter/' . $encounterId . '>';
            },
            $modifiedTtl
        );

        $modifiedTtl = preg_replace_callback(
            '/<' . $escapedBase . '([^\/]+)\/item\/([^\/]+)\/encounter\/([^>]+)>/',
            function ($matches) use (&$replacements, $localBaseUri, $resolveExcavationId) {
                $setId = $matches[1];
                $encounterId = $matches[3];

                $excId = $resolveExcavationId($setId);
                $excavationIdentifier = $excId ?: 'excavation';

                $newUri = '<' . $localBaseUri . $setId . '/excavation/' . $excavationIdentifier . '/encounter/' . $encounterId . '>';

                $replacements++;

                return $newUri;
            },
            $modifiedTtl
        );

        $modifiedTtl = str_replace('<<', '<', $modifiedTtl);
        $modifiedTtl = str_replace('>>', '>', $modifiedTtl);

        error_log('normalized: ' . $modifiedTtl, 3, OMEKA_PATH . '/logs/normalizeeeee_uris.log');

        return $modifiedTtl;
    }
}
