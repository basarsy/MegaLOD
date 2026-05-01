<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Ttl;

/**
 * RDF property/local-name to short UI labels used in detail views.
 */
final class VocabularyLabelService
{
    private const LABEL_MAP = [
        'dcterms:title' => 'Title',
        'dcterms:identifier' => 'Identifier',
        'dcterms:description' => 'Description',
        'bibo:annotates' => 'Annotations',
        'crm:P44_has_condition' => 'Condition',
        'crm:P2_has_type' => 'Type',
        'crm:P43_has_dimension' => 'Dimension',
        'geo:lat' => 'Latitude',
        'geo:long' => 'Longitude',
        'ah:shape' => 'Shape',
        'ah:variant' => 'Variant',
        'ah:hasMorphology' => 'Morphology',
        'excav:elongationIndex' => 'Elongation Index',
        'excav:thicknessIndex' => 'Thickness Index',
        'schema:height' => 'Height',
        'schema:width' => 'Width',
        'schema:depth' => 'Thickness',
        'schema:weight' => 'Weight',
    ];

    public function getHumanReadableLabel(string $term): string
    {
        if (isset(self::LABEL_MAP[$term])) {
            return self::LABEL_MAP[$term];
        }

        $label = $term;
        if (strpos($label, ':') !== false) {
            $parts = explode(':', $label);
            $label = end($parts);
        }

        $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $label);

        return ucwords((string) $label);
    }
}
