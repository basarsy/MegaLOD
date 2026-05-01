<?php

namespace AddTriplestore\Service\Ttl;

/**
 * Pure TTL string inspection used by upload flows (no Omeka API).
 */
class TtlContentInspectionService
{
    /**
     * @param string|null $uploadType excavation|arrowhead|null
     */
    public function validateUploadType(string $ttlData, ?string $uploadType): void
    {
        if (!$uploadType) {
            return;
        }

        $excavationPatterns = [
            'a excav:Excavation',
            'excav:Excavation',
            'crmarchaeo:A9_Archaeological_Excavation',
            'a crmarchaeo:A9_Archaeological_Excavation',
            'excav:hasPersonInCharge',
            'excav:hasSquare',
            'excav:hasContext',
        ];

        $arrowheadPatterns = [
            'a ah:Arrowhead',
            'ah:Arrowhead',
            'a excav:Item',
            'excav:Item',
            'ah:shape',
            'ah:variant',
            'ah:hasMorphology',
            'ah:hasChipping',
        ];

        $isExcavation = false;
        foreach ($excavationPatterns as $pattern) {
            if (strpos($ttlData, $pattern) !== false) {
                $isExcavation = true;
                break;
            }
        }

        $isArrowhead = false;
        foreach ($arrowheadPatterns as $pattern) {
            if (strpos($ttlData, $pattern) !== false) {
                $isArrowhead = true;
                break;
            }
        }

        if ($uploadType === 'excavation' && !$isExcavation) {
            if ($isArrowhead) {
                return;
            }
            throw new \Exception('Invalid data type for excavation upload.');
        } elseif ($uploadType === 'arrowhead' && !$isArrowhead) {
            throw new \Exception('Invalid data type for Arrowhead upload.');
        }
    }

    public function extractExcavationIdentifier(string $ttlData): ?string
    {
        if (preg_match('/dct:identifier\s+"([^"]+)"\^\^xsd:literal/', $ttlData, $matches)) {
            return $matches[1];
        }

        if (preg_match('/dcterms:identifier\s+"([^"]+)"/', $ttlData, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return array{location: ?string, archaeologist: ?string}
     */
    public function extractExcavationMetadataFromTtl(string $ttlData): array
    {
        $metadata = [
            'location' => null,
            'archaeologist' => null,
        ];

        if (preg_match('/dbo:informationName\s+"([^"]+)"/i', $ttlData, $matches)) {
            $metadata['location'] = $matches[1];
        }

        if (preg_match('/foaf:name\s+"([^"]+)"/i', $ttlData, $matches)) {
            $metadata['archaeologist'] = $matches[1];
        }

        return $metadata;
    }
}
