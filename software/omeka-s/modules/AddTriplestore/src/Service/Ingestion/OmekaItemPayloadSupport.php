<?php

namespace AddTriplestore\Service\Ingestion;

/**
 * Pure helpers for Omeka JSON-LD item payloads (testable without HTTP or Omeka runtime).
 */
final class OmekaItemPayloadSupport
{
    /**
     * @param array<string, mixed> $itemData
     */
    public static function extractDctermsIdentifier(array $itemData): ?string
    {
        if (!isset($itemData['dcterms:identifier']) || !is_array($itemData['dcterms:identifier'])) {
            return null;
        }

        foreach ($itemData['dcterms:identifier'] as $identifierData) {
            if (is_array($identifierData) && isset($identifierData['@value'])) {
                return (string) $identifierData['@value'];
            }
        }

        return null;
    }
}
