<?php

namespace AddTriplestore\Service\Ttl;

use AddTriplestore\Service\Ingestion\OmekaResourceLookupService;

/**
 * Turtle generation for arrowhead form payloads (item graph + related excavation entities).
 * Site-specific identifiers and SPARQL lookups are resolved by the controller (or a future context service) and passed in.
 */
final class ArrowheadTtlBuilder
{
    /** @var TtlUriHelper */
    private $ttlUriHelper;

    /** @var OmekaResourceLookupService */
    private $omekaResourceLookupService;

    /** @var string */
    private $localBaseUri;

    public function __construct(
        TtlUriHelper $ttlUriHelper,
        OmekaResourceLookupService $omekaResourceLookupService,
        string $megalodLocalBaseUri
    ) {
        $this->ttlUriHelper = $ttlUriHelper;
        $this->omekaResourceLookupService = $omekaResourceLookupService;
        $this->localBaseUri = $megalodLocalBaseUri;
    }

    /**
     * @param array<string, mixed> $formData normalized arrowhead field map (same keys as legacy processArrowheadFormData)
     * @param array<string, mixed>|null $locationData row from SPARQL/Omeka context; optional even when $realLocationUri is set
     */
    public function buildFromFormData(
        array $formData,
        string $itemSetId,
        ?string $excavationIdentifier,
        ?string $realLocationUri,
        ?array $locationData
    ): string {
        $arrowheadId = !empty($formData['arrowhead_identifier'])
            ? $formData['arrowhead_identifier']
            : 'AH-' . uniqid();

        $baseUri = "{$this->localBaseUri}{$itemSetId}/item/{$arrowheadId}";

        $arrowheadUri = $baseUri;
        $morphologyUri = "$baseUri/morphology/$arrowheadId";
        $chippingUri = "$baseUri/chipping/$arrowheadId";
        $gpsUri = "$baseUri/gps/$arrowheadId";

        $ttl = $this->ttlUriHelper->getTtlPrefixes();

        $ttl .= "<$arrowheadUri> a ah:Arrowhead, excav:Item;\n";
        $ttl .= "    dct:identifier \"$arrowheadId\"^^xsd:literal;\n";

        if (!empty($formData['selected_square'])) {
            $squareItemId = $formData['selected_square'];
            $realSquareId = $this->omekaResourceLookupService->getRealIdentifierFromOmekaItem($squareItemId);
            $excavationIdForSquare = $excavationIdentifier ?? 'excavation';
            if ($realSquareId) {
                $squareUri = "{$this->localBaseUri}{$itemSetId}/excavation/{$excavationIdForSquare}/square/{$realSquareId}";
                $ttl .= "    excav:foundInSquare <$squareUri>;\n";
            }
        }

        if ($realLocationUri) {
            $ttl .= "    excav:foundInLocation <$realLocationUri>;\n";
        }

        if ($excavationIdentifier) {
            $excavationUri = "{$this->localBaseUri}{$itemSetId}/excavation/{$excavationIdentifier}";
            $ttl .= "    excav:foundInExcavation <$excavationUri>;\n";
        }

        if (
            $realLocationUri
            && empty($formData['selected_square'])
            && empty($formData['selected_context'])
            && empty($formData['selected_svu'])
        ) {
            $ttl .= "    excav:foundInLocation <$realLocationUri>;\n";
        }

        if (!empty($formData['arrowhead_annotation'])) {
            $ttl .= '    dbo:Annotation "' . $formData['arrowhead_annotation'] . "\"^^xsd:literal;\n";
        }

        if (!empty($formData['condition_state'])) {
            $value = (stripos($formData['condition_state'], 'true') !== false) ? 'true' : 'false';
            $ttl .= "    crm:E3_Condition_State \"$value\"^^xsd:boolean;\n";
        }

        if (!empty($formData['arrowhead_type'])) {
            $value = (stripos($formData['arrowhead_type'], 'true') !== false) ? 'true' : 'false';
            $ttl .= "    crm:E55_Type \"$value\"^^xsd:boolean;\n";
        }

        if (!empty($formData['elongation_index'])) {
            $ttl .= '    excav:elongationIndex <https://purl.org/megalod/kos/MegaLOD-IndexElongation/'
                . strtolower($formData['elongation_index']) . ">;\n";
        }

        if (!empty($formData['thickness_index'])) {
            $ttl .= '    excav:thicknessIndex <https://purl.org/megalod/kos/MegaLOD-IndexThickness/'
                . strtolower($formData['thickness_index']) . ">;\n";
        }

        if (!empty($formData['arrowhead_material'])) {
            $ttl .= '    crm:E57_Material <' . $formData['arrowhead_material'] . ">;\n";
        }

        if (!empty($formData['arrowhead_shape'])) {
            $shapeMapping = [
                'triangle' => 'triangle',
                'lozenge-shaped' => 'losangular',
                'losangular' => 'losangular',
                'stemmed' => 'stemmed',
            ];

            $shapeSafe = isset($shapeMapping[$formData['arrowhead_shape']])
                ? $shapeMapping[$formData['arrowhead_shape']]
                : strtolower(str_replace('-', '-', $formData['arrowhead_shape']));

            $ttl .= "    ah:shape <https://purl.org/megalod/kos/ah-shape/{$shapeSafe}>;\n";
        }

        if (!empty($formData['arrowhead_variant'])) {
            $variantSafe = strtolower($formData['arrowhead_variant']);
            $ttl .= "    ah:variant <https://purl.org/megalod/kos/ah-variant/{$variantSafe}>;\n";
        }

        if (!empty($formData['gps_latitude']) && !empty($formData['gps_longitude'])) {
            $gpsUri = "$baseUri/gps/$arrowheadId";
            $ttl .= "    excav:hasGPSCoordinates <$gpsUri>;\n";
        }

        if (!empty($formData['encounter_date'])) {
            $ttl .= '    dct:date "' . $formData['encounter_date'] . "\"^^xsd:literal;\n";
        }

        $measurementBlocks = '';
        $processedMeasurements = [];

        $measurements = [
            'height' => 'height',
            'width' => 'width',
            'weight' => 'weight',
        ];

        foreach ($measurements as $measurement => $property) {
            $valueKey = $measurement;
            $unitKey = $measurement . '_unit';

            if (!empty($formData[$valueKey])) {
                $measurementUri = "$baseUri/typometry/$arrowheadId-$measurement";

                if ($measurement === 'weight') {
                    $ttl .= "    schema:weight <$measurementUri>;\n";
                    $measurementBlocks .= "<$measurementUri> a excav:Weight;\n";
                    $measurementBlocks .= '    schema:value "' . $formData[$valueKey] . "\"^^xsd:decimal;\n";

                    if (!empty($formData[$unitKey]) && !isset($processedMeasurements[$measurementUri])) {
                        $weightUnit = $formData[$unitKey];
                        $measurementBlocks .= "    schema:UnitCode <{$weightUnit}>;\n";
                        $processedMeasurements[$measurementUri] = true;
                    }
                    $measurementBlocks .= "    .\n\n";
                } else {
                    $ttl .= "    schema:$property <$measurementUri>;\n";
                    $measurementBlocks .= "<$measurementUri> a excav:TypometryValue;\n";
                    $measurementBlocks .= '    schema:value "' . $formData[$valueKey] . "\"^^xsd:decimal;\n";

                    if (!empty($formData[$unitKey]) && !isset($processedMeasurements[$measurementUri])) {
                        $measurementBlocks .= '    schema:UnitCode <' . $formData[$unitKey] . ">;\n";
                        $processedMeasurements[$measurementUri] = true;
                    }
                    $measurementBlocks .= "    .\n\n";
                }
            }
        }

        if (!empty($formData['thickness'])) {
            $thicknessUri = "$baseUri/typometry/$arrowheadId-thickness";
            if (!isset($processedMeasurements[$thicknessUri])) {
                $ttl .= "    schema:depth <$thicknessUri>;\n";
                $measurementBlocks .= "<$thicknessUri> a excav:TypometryValue;\n";
                $measurementBlocks .= '    schema:value "' . $formData['thickness'] . "\"^^xsd:decimal;\n";
                if (!empty($formData['thickness_unit'])) {
                    $measurementBlocks .= '    schema:UnitCode <' . $formData['thickness_unit'] . ">;\n";
                }
                $measurementBlocks .= "    .\n\n";
                $processedMeasurements[$thicknessUri] = true;
            }
        }

        if (!empty($formData['body_length'])) {
            $bodyLengthUri = "$baseUri/typometry/$arrowheadId-hasBodyLength";
            if (!isset($processedMeasurements[$bodyLengthUri])) {
                $ttl .= "    ah:hasBodyLength <$bodyLengthUri>;\n";
                $measurementBlocks .= "<$bodyLengthUri> a excav:TypometryValue;\n";
                $measurementBlocks .= '    schema:value "' . $formData['body_length'] . "\"^^xsd:decimal;\n";
                if (!empty($formData['body_length_unit'])) {
                    $measurementBlocks .= '    schema:UnitCode <' . $formData['body_length_unit'] . ">;\n";
                }
                $measurementBlocks .= "    .\n\n";
                $processedMeasurements[$bodyLengthUri] = true;
            }
        }

        if (!empty($formData['base_length'])) {
            $baseLengthUri = "$baseUri/typometry/$arrowheadId-hasBaseLength";
            if (!isset($processedMeasurements[$baseLengthUri])) {
                $ttl .= "    ah:hasBaseLength <$baseLengthUri>;\n";
                $measurementBlocks .= "<$baseLengthUri> a excav:TypometryValue;\n";
                $measurementBlocks .= '    schema:value "' . $formData['base_length'] . "\"^^xsd:decimal;\n";
                if (!empty($formData['base_length_unit'])) {
                    $measurementBlocks .= '    schema:UnitCode <' . $formData['base_length_unit'] . ">;\n";
                }
                $measurementBlocks .= "    .\n\n";
                $processedMeasurements[$baseLengthUri] = true;
            }
        }

        $hasChippingData = !empty($formData['chipping_mode'])
            || !empty($formData['chipping_amplitude'])
            || !empty($formData['chipping_direction']);

        if ($hasChippingData) {
            $ttl .= "    ah:hasChipping <$chippingUri>;\n";
        }

        $hasMorphologyData = !empty($formData['point_definition'])
            || !empty($formData['body_symmetry'])
            || !empty($formData['arrowhead_base']);

        if ($hasMorphologyData) {
            $ttl .= "    ah:hasMorphology <$morphologyUri>;\n";
        }

        $coordinatesData = null;
        if (!empty($formData['x_coordinate']) && !empty($formData['y_coordinate'])) {
            $coordinatesUri = "$baseUri/coordinatesInSquare/" . substr($arrowheadId, 3);
            $ttl .= "    excav:hasCoordinatesInSquare <$coordinatesUri>;\n";

            $coordinatesData = [
                'uri' => $coordinatesUri,
                'x' => $formData['x_coordinate'],
                'x_unit' => !empty($formData['x_coordinate_unit']) ? $formData['x_coordinate_unit'] : 'CMT',
                'y' => $formData['y_coordinate'],
                'y_unit' => !empty($formData['y_coordinate_unit']) ? $formData['y_coordinate_unit'] : 'CMT',
                'z' => !empty($formData['z_coordinate']) ? $formData['z_coordinate'] : null,
                'z_unit' => !empty($formData['z_coordinate_unit']) ? $formData['z_coordinate_unit'] : 'CMT',
            ];
        }

        if (!empty($formData['images'])) {
            $images = $formData['images'];
            if (is_array($images)) {
                foreach ($images as $image) {
                    if (!empty($image)) {
                        $imageParts = parse_url($image);
                        if (isset($imageParts['path'])) {
                            $originalFilename = basename($imageParts['path']);
                            $sanitizedFilename = $this->ttlUriHelper->sanitizeFilenameForUri($originalFilename);

                            $imageUri = str_replace($originalFilename, $sanitizedFilename, $image);
                            $ttl .= "    edm:Webresource <$imageUri>;\n";
                        } else {
                            $ttl .= "    edm:Webresource <$image>;\n";
                        }
                    }
                }
            } elseif (!empty($images)) {
                $imageParts = parse_url($images);
                if (isset($imageParts['path'])) {
                    $originalFilename = basename($imageParts['path']);
                    $sanitizedFilename = $this->ttlUriHelper->sanitizeFilenameForUri($originalFilename);

                    $imageUri = str_replace($originalFilename, $sanitizedFilename, $images);
                    $ttl .= "    edm:Webresource <$imageUri>;\n";
                } else {
                    $ttl .= "    edm:Webresource <$images>;\n";
                }
            }
        }

        $ttl .= "    .\n\n";

        $ttl .= "\n# =========== RESOURCE DECLARATIONS ===========\n\n";

        if ($excavationIdentifier) {
            $excavationUri = "{$this->localBaseUri}{$itemSetId}/excavation/{$excavationIdentifier}";
            $ttl .= "<$excavationUri> a excav:Excavation ;\n";
            $ttl .= "    dct:identifier \"$excavationIdentifier\"^^xsd:literal .\n\n";
        }

        if ($realLocationUri) {
            $ttl .= "<$realLocationUri> a excav:Location ;\n";

            if ($locationData && !empty($locationData['name'])) {
                $ttl .= '    dbo:informationName "' . $locationData['name'] . "\"^^xsd:literal ;\n";

                if (!empty($locationData['district'])) {
                    $ttl .= '    dbo:district <' . $locationData['district'] . "> ;\n";
                }
                if (!empty($locationData['parish'])) {
                    $ttl .= '    dbo:parish <' . $locationData['parish'] . "> ;\n";
                }
                if (!empty($locationData['country'])) {
                    $ttl .= '    dbo:Country <' . $locationData['country'] . "> ;\n";
                }
            }

            $ttl = rtrim($ttl, " ;\n") . " .\n\n";
        }

        if (!empty($formData['selected_square'])) {
            $squareItemId = $formData['selected_square'];
            $realSquareId = $this->omekaResourceLookupService->getRealIdentifierFromOmekaItem($squareItemId);
            if ($realSquareId) {
                $squareUri = "{$this->localBaseUri}{$itemSetId}/excavation/{$excavationIdentifier}/square/{$realSquareId}";
                $ttl .= "<$squareUri> a excav:Square ;\n";
                $ttl .= "    dct:identifier \"$realSquareId\"^^xsd:literal .\n\n";
            }
        }

        if (!empty($formData['selected_context'])) {
            $contextItemId = $formData['selected_context'];
            $realContextId = $this->omekaResourceLookupService->getRealIdentifierFromOmekaItem($contextItemId);
            if ($realContextId) {
                $contextUri = "{$this->localBaseUri}{$itemSetId}/excavation/{$excavationIdentifier}/context/{$realContextId}";
                $ttl .= "<$contextUri> a excav:Context ;\n";
                $ttl .= "    dct:identifier \"$realContextId\"^^xsd:literal .\n\n";
            }
        }

        if (!empty($formData['selected_svu'])) {
            $svuItemId = $formData['selected_svu'];
            $realSvuId = $this->omekaResourceLookupService->getRealIdentifierFromOmekaItem($svuItemId);

            if ($realSvuId) {
                $svuUri = "{$this->localBaseUri}{$itemSetId}/excavation/{$excavationIdentifier}/svu/{$realSvuId}";
                $ttl .= "<$svuUri> a excav:StratigraphicVolumeUnit ;\n";
                $ttl .= "    dct:identifier \"$realSvuId\"^^xsd:literal .\n\n";
            }
        }

        if ($coordinatesData) {
            $ttl .= "<{$coordinatesData['uri']}> a excav:Coordinates;\n";
            $ttl .= "    schema:value \"{$coordinatesData['x']} {$coordinatesData['x_unit']}\"^^xsd:literal;\n";
            $ttl .= "    schema:value \"{$coordinatesData['y']} {$coordinatesData['y_unit']}\"^^xsd:literal;\n";

            if ($coordinatesData['z']) {
                $ttl .= "    schema:value \"{$coordinatesData['z']} {$coordinatesData['z_unit']}\"^^xsd:literal;\n";
            }

            $ttl .= "    .\n\n";
        }

        if (!empty($formData['gps_latitude']) && !empty($formData['gps_longitude'])) {
            $ttl .= "<$gpsUri> a excav:GPSCoordinates;\n";
            $ttl .= '    geo:lat "' . $formData['gps_latitude'] . "\"^^xsd:decimal;\n";
            $ttl .= '    geo:long "' . $formData['gps_longitude'] . "\"^^xsd:decimal;\n";
            $ttl .= "    .\n\n";
        }

        if ($hasMorphologyData) {
            $ttl .= "<$morphologyUri> a ah:Morphology;\n";

            if (!empty($formData['point_definition'])) {
                $value = (stripos($formData['point_definition'], 'true') !== false) ? 'true' : 'false';
                $ttl .= "    ah:point \"$value\"^^xsd:boolean;\n";
            }

            if (!empty($formData['body_symmetry'])) {
                $value = (stripos($formData['body_symmetry'], 'true') !== false) ? 'true' : 'false';
                $ttl .= "    ah:body \"$value\"^^xsd:boolean;\n";
            }

            if (!empty($formData['arrowhead_base'])) {
                $baseSafe = strtolower($formData['arrowhead_base']);
                $ttl .= "    ah:base <https://purl.org/megalod/kos/ah-base/{$baseSafe}>;\n";
            }

            $ttl .= "    .\n\n";
        }

        if ($hasChippingData) {
            $ttl .= "<$chippingUri> a ah:Chipping;\n";

            if (!empty($formData['chipping_mode'])) {
                $modeSafe = strtolower(str_replace('-', '-', $formData['chipping_mode']));
                $ttl .= "    ah:chippingMode <https://purl.org/megalod/kos/ah-chippingMode/{$modeSafe}>;\n";
            }

            if (!empty($formData['chipping_amplitude'])) {
                $value = (stripos($formData['chipping_amplitude'], 'true') !== false) ? 'true' : 'false';
                $ttl .= "    ah:chippingAmplitude \"$value\"^^xsd:boolean;\n";
            }

            if (!empty($formData['chipping_direction'])) {
                $directionSafe = strtolower($formData['chipping_direction']);
                $ttl .= "    ah:chippingDirection <https://purl.org/megalod/kos/ah-chippingDirection/{$directionSafe}>;\n";
            }

            if (!empty($formData['chipping_orientation'])) {
                $value = (stripos($formData['chipping_orientation'], 'true') !== false) ? 'true' : 'false';
                $ttl .= "    ah:chippingOrientation \"$value\"^^xsd:boolean;\n";
            }

            if (!empty($formData['chipping_delineation'])) {
                $delineationSafe = strtolower($formData['chipping_delineation']);
                $ttl .= "    ah:chippingDelineation <https://purl.org/megalod/kos/ah-chippingDelineation/{$delineationSafe}>;\n";
            }

            for ($i = 1; $i <= 3; $i++) {
                $lateralKey = 'chipping_location_lateral_' . $i;
                if (!empty($formData[$lateralKey])) {
                    $locationSafe = strtolower($formData[$lateralKey]);
                    $ttl .= "    ah:chippingLocationSide <https://purl.org/megalod/kos/ah-chippingLocation/{$locationSafe}>;\n";
                }
            }

            for ($i = 1; $i <= 3; $i++) {
                $transversalKey = 'chipping_location_transversal_' . $i;
                if (!empty($formData[$transversalKey])) {
                    $locationSafe = strtolower($formData[$transversalKey]);
                    $ttl .= "    ah:chippingLocationTransversal <https://purl.org/megalod/kos/ah-chippingLocation/{$locationSafe}>;\n";
                }
            }

            if (!empty($formData['chipping_shape'])) {
                $shapeSafe = strtolower($formData['chipping_shape']);
                $ttl .= "    ah:chippingShape <https://purl.org/megalod/kos/ah-chippingShape/{$shapeSafe}>;\n";
            }

            $ttl .= "    .\n\n";
        }

        $ttl .= $measurementBlocks;

        return $ttl;
    }
}
