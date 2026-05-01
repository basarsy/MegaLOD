<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Ttl;

use AddTriplestore\Service\MegalodConfig;
use Omeka\Api\Representation\ItemRepresentation;

/**
 * Rebuild Turtle for download from an Omeka item resource preserving original excavation URIs.
 */
final class ArrowheadCanonicalTtlExporter
{
    /** @var string */
    private $publicBaseUri;

    /** @var string */
    private $localBaseUriStr;

    /** @var TtlUriHelper */
    private $ttlUriHelper;

    public function __construct(MegalodConfig $megalodConfig, TtlUriHelper $ttlUriHelper)
    {
        $this->publicBaseUri = $megalodConfig->getMegalodPublicBaseUri();
        $this->localBaseUriStr = $megalodConfig->getMegalodLocalBaseUri();
        $this->ttlUriHelper = $ttlUriHelper;
    }

    public function generateFromItemRepresentation(ItemRepresentation $resource)
    {
        $values = $resource->values();

        // Extract the original normalized URI from the context references
        $originalBaseUri = $this->extractOriginalBaseUri($values, $resource);
        if ($originalBaseUri == null) {

            return "# No valid excavation context found for resource: " . $resource->id() . "\n";
        }
        $identifier = $this->extractIdentifierFromResource($resource);

        // Use the original normalized URI structure
        $arrowheadUri = "$originalBaseUri/item/$identifier";

        // Start building TTL with single resource comment
        $ttl = "# Resource: " . $resource->displayTitle() . "\n\n";

        // Main arrowhead declaration
        $ttl .= "<$arrowheadUri> a excav:Item, ah:Arrowhead ;\n";

        // Add identifier
        if ($identifier) {
            $ttl .= "    dct:identifier \"$identifier\"^^xsd:literal ;\n";
        }

        // Add basic properties
        $ttl .= $this->processArrowheadCorePropertiesWithOriginalUris($values, $arrowheadUri, $originalBaseUri);

        // Add measurement references
        $ttl .= $this->addMeasurementReferences($values, $arrowheadUri, $identifier);

        // Add morphology reference
        if ($this->hasMorphologyData($values)) {
            $ttl .= "    ah:hasMorphology <$arrowheadUri/morphology/$identifier-morphology> ;\n";
        }

        // Add chipping reference  
        if ($this->hasChippingData($values)) {
            $ttl .= "    ah:hasChipping <$arrowheadUri/chipping/$identifier-chipping> ;\n";
        }

        // Add coordinates reference
        if ($this->hasCoordinatesData($values)) {
            $ttl .= "    excav:hasCoordinatesInSquare <$arrowheadUri/coordinates/$identifier-coordinates> ;\n";
        }

        // Add GPS coordinates reference
        if ($this->hasGpsData($values)) {
            $excavationId = $this->extractExcavationId($values);
            $ttl .= "    excav:hasGPSCoordinates <$originalBaseUri/excavation/$excavationId/gps/$identifier-gps> ;\n";
        }

        // Close main resource (remove trailing semicolon and add period)
        $ttl = rtrim($ttl, " ;\n") . " .\n\n";

        // Now add all the separate objects in order
        $ttl .= $this->processMeasurementsWithOriginalUris($values, $arrowheadUri, $identifier);
        $ttl .= $this->processMorphologyWithOriginalUris($values, $arrowheadUri, $identifier);
        $ttl .= $this->processChippingWithOriginalUris($values, $arrowheadUri, $identifier);
        $ttl .= $this->processCoordinatesWithOriginalUris($values, $arrowheadUri, $identifier);
        $ttl .= $this->processGPSWithOriginalUris($values, $originalBaseUri, $identifier);

        return $this->ttlUriHelper->getTtlPrefixes() . "\n\n" . $ttl;
    }

    /**
     * This method adds measurement references to the TTL string.
     * It checks for various measurement values and constructs the appropriate URIs.
     * @param array $values The values from the resource
     * @param string $arrowheadUri The base URI for the arrowhead
     * @param string $identifier The identifier of the resource
     * @return string The TTL string with measurement references
     */
    private function addMeasurementReferences($values, $arrowheadUri, $identifier)
    {
        $ttl = "";

        $measurements = [
            'Height' => ['height', 'schema:height'],
            'Width' => ['width', 'schema:width'],
            'Weight' => ['weight', 'schema:weight'], 
            'Thickness' => ['depth', 'schema:depth'],
            'Body Length' => ['bodylength', 'ah:hasBodyLength'],
            'Base Length' => ['baselength', 'ah:hasBaseLength']
        ];

        foreach ($measurements as $label => $config) {
            $suffix = $config[0];
            $property = $config[1];

            if (isset($values[$label]) && !empty($values[$label]['values'])) {
                if ($property === 'schema:weight') {
                    $ttl .= "    schema:weight <$arrowheadUri/weight/$identifier-weight> ;\n";
                } elseif (strpos($property, 'ah:') === 0) {
                    $ttl .= "    $property <$arrowheadUri/$suffix/$identifier-$suffix> ;\n";
                } else {
                    $propertyName = str_replace('schema:', '', $property);
                    $ttl .= "    schema:$propertyName <$arrowheadUri/typometry/$identifier-$suffix> ;\n";
                }
            }
        }

        return $ttl;
    }

    /**
     * This method checks if the values contain morphology data.
     * It looks for specific properties that indicate morphology information.
     * @param array $values The values from the resource
     * @return bool True if morphology data is present, false otherwise
     */
    private function hasMorphologyData($values)
    {
        return isset($values['ah:point']) || isset($values['ah:body']) || isset($values['ah:base']);
    }
    /**
     * This method processes the chipping data and generates the TTL string.
     * @param mixed $values
     * @return bool
     */
    private function hasChippingData($values)
    {
        $chippingProperties = ['ah:chippingMode', 'ah:chippingAmplitude', 'ah:chippingDirection', 
                              'ah:chippingOrientation', 'ah:chippingDelineation', 'ah:chippingLocationSide',
                              'ah:chippingLocationTransversal', 'ah:chippingShape'];

        foreach ($chippingProperties as $prop) {
            if (isset($values[$prop])) {
                return true;
            }
        }
        return false;
    }

    /**
     * This method checks if the values contain coordinates data.
     * @param mixed $values
     * @return bool
     */
    private function hasGpsData($values)
    {
        return isset($values['excavation:hasGPSCoordinates']);
    }
    /**
     * This method extracts the excavation ID from the values.
     * It looks for the excavation context reference and extracts the ID from the URI.
     * @param mixed $values
     * @param mixed $resource
     * @return string|null The excavation ID or null if not found
     */
    private function extractOriginalBaseUri($values, $resource)
    {
        $contextProperties = [
            'excavation:foundInLocation',
            'excavation:foundInSquare', 
            'excavation:foundInContext',
            'excavation:foundInSVU'
        ];

        foreach ($contextProperties as $property) {
            if (isset($values[$property])) {
                foreach ($values[$property]['values'] as $value) {
                    if ($value->uri()) {
                        $uri = $value->uri();
                        $escapedPub = preg_quote($this->publicBaseUri, '/');
                        if (preg_match('/^(' . $escapedPub . '\d+)\/excavation\/[^\/]+\//', $uri, $matches)) {
                            return $matches[1];
                        }
                    }
                }
            }
        }

        $itemSets = $resource->itemSets();
        if (!empty($itemSets)) {
            $itemSetId = $itemSets[0]->id();
            return "{$this->localBaseUriStr}$itemSetId";
        }

        return null;
    }

    /**
     * This method extracts the identifier from the resource.
     * It looks for the dcterms:identifier property and returns its value.
     * If not found, it generates a default identifier based on the resource ID.
     * @param mixed $resource The resource from which to extract the identifier
     * @return string The extracted or generated identifier
     */
    private function extractIdentifierFromResource($resource)
    {
        $values = $resource->values();

        if (isset($values['dcterms:identifier'])) {
            foreach ($values['dcterms:identifier']['values'] as $value) {
                return $value->value();
            }
        }

        return 'item-' . $resource->id();
    }

    /**
     * This method processes the core properties of an arrowhead resource
     * and generates the TTL string using the original URIs.
     * @param mixed $values
     * @param mixed $arrowheadUri
     * @param mixed $originalBaseUri
     * @return string
     */
    private function processArrowheadCorePropertiesWithOriginalUris($values, $arrowheadUri, $originalBaseUri)
    {
        $ttl = "";
        $entitiesToDeclare = [];

        // Process description
        if (isset($values['dcterms:description'])) {
            foreach ($values['dcterms:description']['values'] as $value) {
                $ttl .= "    dbo:Annotation \"" . $this->escapeTtlString($value->value()) . "\"^^xsd:literal ;\n";
            }
        }

        // Process condition state as boolean
        if (isset($values['crm:P44_has_condition'])) {
            foreach ($values['crm:P44_has_condition']['values'] as $value) {
                $boolValue = (strtolower($value->value()) === 'true') ? 'true' : 'false';
                $ttl .= "    crm:E3_Condition_State $boolValue ;\n";
            }
        }

        // Process type as boolean
        if (isset($values['crm:P2_has_type'])) {
            foreach ($values['crm:P2_has_type']['values'] as $value) {
                $boolValue = (strtolower($value->value()) === 'true') ? 'true' : 'false';
                $ttl .= "    crm:E55_Type $boolValue ;\n";
            }
        }

        // Process material with proper URI
        if (isset($values['schema:material'])) {
            foreach ($values['schema:material']['values'] as $value) {
                $materialUri = "http://vocab.getty.edu/page/aat/" . $value->value();
                $ttl .= "    crm:E57_Material <$materialUri> ;\n";
            }
        }

        // Process shape with controlled vocabulary URI (preserve original)
        if (isset($values['ah:shape'])) {
            foreach ($values['ah:shape']['values'] as $value) {
                $shapeValue = strtolower($value->value());
                $ttl .= "    ah:shape <https://purl.org/megalod/kos/ah-shape/$shapeValue> ;\n";
            }
        }

        if (isset($values['ah:variant'])) {
            foreach ($values['ah:variant']['values'] as $value) {
                $variantValue = strtolower($value->value());
                $ttl .= "    ah:variant <https://purl.org/megalod/kos/ah-variant/$variantValue> ;\n";
            }
        }

        if (isset($values['excavation:elongationIndex'])) {
            foreach ($values['excavation:elongationIndex']['values'] as $value) {
                $ttl .= "    excav:elongationIndex <https://purl.org/megalod/kos/MegaLOD-IndexElongation/" . $value->value() . "> ;\n";
            }
        }

        if (isset($values['excavation:thicknessIndex'])) {
            foreach ($values['excavation:thicknessIndex']['values'] as $value) {
                $ttl .= "    excav:thicknessIndex <https://purl.org/megalod/kos/MegaLOD-IndexThickness/" . $value->value() . "> ;\n";
            }
        }

        $contextProperties = [
            'excavation:foundInLocation' => 'excav:foundInLocation',
            'excavation:foundInSquare' => 'excav:foundInSquare', 
            'excavation:foundInContext' => 'excav:foundInContext',
            'excavation:foundInSVU' => 'excav:foundInSVU'
        ];

        foreach ($contextProperties as $omekaProperty => $ttlProperty) {
            if (isset($values[$omekaProperty])) {
                foreach ($values[$omekaProperty]['values'] as $value) {
                    if ($value->uri()) {
                        $ttl .= "    $ttlProperty <" . $value->uri() . "> ;\n";
                    }
                }
            }
        }

        if (isset($values['district'])) {
            foreach ($values['district']['values'] as $value) {
                $districtName = $value->value();
                $districtSlug = str_replace(' ', '_', $districtName);
                $districtUri = "http://dbpedia.org/resource/$districtSlug";
                $ttl .= "    dbo:district <$districtUri> ;\n";
                $entitiesToDeclare['district'] = [
                    'uri' => $districtUri,
                    'name' => $districtName
                ];
            }
        }

        if (isset($values['parish'])) {
            foreach ($values['parish']['values'] as $value) {
                $parishName = $value->value();
                $parishSlug = str_replace(' ', '_', $parishName);
                $parishUri = "http://dbpedia.org/resource/$parishSlug";
                $ttl .= "    dbo:parish <$parishUri> ;\n";
                $entitiesToDeclare['parish'] = [
                    'uri' => $parishUri,
                    'name' => $parishName
                ];
            }
        }

        if (isset($values['Country'])) {
            foreach ($values['Country']['values'] as $value) {
                $countryName = $value->value();
                $countrySlug = str_replace(' ', '_', $countryName);
                $countryUri = "http://dbpedia.org/resource/$countrySlug";
                $ttl .= "    dbo:Country <$countryUri> ;\n";

            }
        }

        if (isset($values['dcterms:date'])) {
            foreach ($values['dcterms:date']['values'] as $value) {
                $ttl .= "    dct:date \"" . $value->value() . "\"^^xsd:literal ;\n";
            }
        }

        if (isset($values['dcterms:hasFormat'])) {
            foreach ($values['dcterms:hasFormat']['values'] as $value) {
                if ($value->uri()) {
                    $ttl .= "    edm:Webresource <" . $value->uri() . "> ;\n";
                }
            }
        }

        if (!empty($entitiesToDeclare)) {
            $ttl .= "\n# Type declarations for referenced resources\n";

            if (isset($entitiesToDeclare['district'])) {
                $ttl .= "<{$entitiesToDeclare['district']['uri']}> a dbo:District .\n";
            }

            if (isset($entitiesToDeclare['parish'])) {
                $ttl .= "<{$entitiesToDeclare['parish']['uri']}> a dbo:Parish .\n";
            }



            $ttl .= "\n";
        }

        return $ttl;
    }

    /**
     * This method processes the measurements with original URIs.
     * @param mixed $values
     * @param mixed $arrowheadUri
     * @param mixed $identifier
     * @return string
     */
    private function processMeasurementsWithOriginalUris($values, $arrowheadUri, $identifier)
    {
        $ttl = "";
        $hasAnyMeasurements = false;

        $measurements = [
            'Height' => ['height', 'schema:height', null],
            'Width' => ['width', 'schema:width', null],
            'Weight' => ['weight', 'schema:weight', null], 
            'Thickness' => ['depth', 'schema:depth', null],
            'Body Length' => ['bodylength', 'ah:hasBodyLength', null],
            'Base Length' => ['baselength', 'ah:hasBaseLength', null],
        ];

        $measurementObjects = "";

        foreach ($measurements as $label => $config) {
            $suffix = $config[0];
            $property = $config[1];
            $defaultUnit = $config[2];

            if (isset($values[$label]) && !empty($values[$label]['values'])) {
                foreach ($values[$label]['values'] as $value) {
                    $measurementValue = $value->value();
                    $hasAnyMeasurements = true;

                    if (preg_match('/^([0-9.]+)\s*([A-Z]+)?/', $measurementValue, $matches)) {
                        $numericValue = $matches[1];
                        $unit = isset($matches[2]) && !empty($matches[2]) ? $matches[2] : $defaultUnit;

                        if ($property === 'schema:weight') {
                            $measurementUri = "$arrowheadUri/weight/$identifier-weight";
                            $measurementObjects .= "<$measurementUri> a excav:Weight ;\n";
                            $measurementObjects .= "    schema:value \"$numericValue\"^^xsd:decimal ;\n";
                            $measurementObjects .= "    schema:UnitCode <http://qudt.org/vocab/unit/$unit> .\n\n";
                        } elseif (strpos($property, 'ah:') === 0) {
                            $propName = str_replace('ah:has', '', $property);
                            $measurementUri = "$arrowheadUri/$suffix/$identifier-$suffix";
                            $measurementObjects .= "<$measurementUri> a excav:TypometryValue ;\n";
                            $measurementObjects .= "    schema:value \"$numericValue\"^^xsd:decimal ;\n";
                            $measurementObjects .= "    schema:UnitCode <http://qudt.org/vocab/unit/$unit> .\n\n";
                        } else {
                            $propName = str_replace('schema:', '', $property);
                            $measurementUri = "$arrowheadUri/typometry/$identifier-$propName";
                            $measurementObjects .= "<$measurementUri> a excav:TypometryValue ;\n";
                            $measurementObjects .= "    schema:value \"$numericValue\"^^xsd:decimal ;\n";
                            $measurementObjects .= "    schema:UnitCode <http://qudt.org/vocab/unit/$unit> .\n\n";
                        }
                    }
                }
            }
        }

        if ($hasAnyMeasurements && $measurementObjects) {
            $ttl .= "# =========== TYPOMETRY VALUES ===========\n\n";
            $ttl .= $measurementObjects;
        }

        return $ttl;
    }



    /**
     * This method processes the morphology data and generates the TTL string.
     * It uses original URIs for morphology properties.
     * @param mixed $values
     * @param mixed $arrowheadUri
     * @param mixed $identifier
     * @return string
     */
    private function processMorphologyWithOriginalUris($values, $arrowheadUri, $identifier)
    {
        $ttl = "";
        $morphologyUri = "$arrowheadUri/morphology/$identifier-morphology";

        $morphologyProperties = ['ah:point', 'ah:body', 'ah:base', 'Point Definition (Sharp/Fractured)', 
                              'Body Symmetry (Symmetrical/Non-symmetrical)', 'Base Type'];

        $hasMorphologyData = false;
        foreach ($morphologyProperties as $prop) {
            if (isset($values[$prop])) {
                $hasMorphologyData = true;
                break;
            }
        }

        if ($hasMorphologyData) {
            $ttl .= "# =========== MORPHOLOGY ===========\n\n";
            $ttl .= "<$morphologyUri> a ah:Morphology ;\n";

            $morphologyStatements = [];

            if (isset($values['ah:point'])) {
                foreach ($values['ah:point']['values'] as $value) {
                    $boolValue = (strtolower($value->value()) === 'true' || strtolower($value->value()) === 'sharp') ? 'true' : 'false';
                    $morphologyStatements[] = "    ah:point $boolValue";
                }
            } else if (isset($values['Point Definition (Sharp/Fractured)'])) {
                foreach ($values['Point Definition (Sharp/Fractured)']['values'] as $value) {
                    $boolValue = (strtolower($value->value()) === 'true' || strtolower($value->value()) === 'sharp') ? 'true' : 'false';
                    $morphologyStatements[] = "    ah:point $boolValue";
                }
            }

            if (isset($values['ah:body'])) {
                foreach ($values['ah:body']['values'] as $value) {
                    $boolValue = (strtolower($value->value()) === 'true' || strtolower($value->value()) === 'symmetrical') ? 'true' : 'false';
                    $morphologyStatements[] = "    ah:body $boolValue";
                }
            } else if (isset($values['Body Symmetry (Symmetrical/Non-symmetrical)'])) {
                foreach ($values['Body Symmetry (Symmetrical/Non-symmetrical)']['values'] as $value) {
                    $boolValue = (strtolower($value->value()) === 'true' || strtolower($value->value()) === 'symmetrical') ? 'true' : 'false';
                    $morphologyStatements[] = "    ah:body $boolValue";
                }
            }

            if (isset($values['ah:base'])) {
                foreach ($values['ah:base']['values'] as $value) {
                    $baseValue = strtolower($value->value());
                    $baseValue = preg_replace('/\s+/', '-', $baseValue); 
                    $morphologyStatements[] = "    ah:base <https://purl.org/megalod/kos/ah-base/$baseValue>";
                }
            } else if (isset($values['Base Type'])) {
                foreach ($values['Base Type']['values'] as $value) {
                    $baseValue = strtolower($value->value());
                    $baseValue = preg_replace('/\s+/', '-', $baseValue); 
                    $morphologyStatements[] = "    ah:base <https://purl.org/megalod/kos/ah-base/$baseValue>";
                }
            }

            if (!empty($morphologyStatements)) {
                $ttl .= implode(" ;\n", $morphologyStatements) . " .\n\n";
            } else {
                $ttl .= "    .\n\n"; 
            }
        }

        return $ttl;
    }

    /**
     * This method processes the chipping data and generates the TTL string.
     * It uses original URIs for chipping properties.
     * It checks for the presence of chipping data and constructs the appropriate URIs.
     * If chipping data is present, it generates a chipping object with the relevant properties.
     * @param mixed $values
     * @param mixed $arrowheadUri
     * @param mixed $identifier
     * @return string
     */
    private function processChippingWithOriginalUris($values, $arrowheadUri, $identifier)
    {
        $ttl = "";
        $chippingUri = "$arrowheadUri/chipping/$identifier-chipping";

        $chippingProperties = ['ah:chippingMode', 'ah:chippingAmplitude', 'ah:chippingDirection', 
                              'ah:chippingOrientation', 'ah:chippingDelineation', 'ah:chippingLocationSide',
                              'ah:chippingLocationTransversal', 'ah:chippingShape'];

        $hasChippingData = false;
        foreach ($chippingProperties as $prop) {
            if (isset($values[$prop])) {
                $hasChippingData = true;
                break;
            }
        }

        if ($hasChippingData) {

            $ttl .= "\n# =========== CHIPPING ===========\n\n";
            $ttl .= "<$chippingUri> a ah:Chipping ;\n";

            if (isset($values['ah:chippingMode'])) {
                foreach ($values['ah:chippingMode']['values'] as $value) {
                    $modeValue = strtolower($value->value());
                    $ttl .= "    ah:chippingMode <https://purl.org/megalod/kos/ah-chippingMode/$modeValue> ;\n";
                }
            }

            if (isset($values['ah:chippingAmplitude'])) {
                foreach ($values['ah:chippingAmplitude']['values'] as $value) {
                    $boolValue = (strtolower($value->value()) === 'true') ? 'true' : 'false';
                    $ttl .= "    ah:chippingAmplitude $boolValue ;\n";
                }
            }

            if (isset($values['ah:chippingDirection'])) {
                foreach ($values['ah:chippingDirection']['values'] as $value) {
                    $directionValue = strtolower($value->value());
                    $ttl .= "    ah:chippingDirection <https://purl.org/megalod/kos/ah-chippingDirection/$directionValue> ;\n";
                }
            }

            if (isset($values['ah:chippingOrientation'])) {
                foreach ($values['ah:chippingOrientation']['values'] as $value) {
                    $boolValue = (strtolower($value->value()) === 'true') ? 'true' : 'false';
                    $ttl .= "    ah:chippingOrientation $boolValue ;\n";
                }
            }

            if (isset($values['ah:chippingDelineation'])) {
                foreach ($values['ah:chippingDelineation']['values'] as $value) {
                    $delineationValue = strtolower($value->value());
                    $ttl .= "    ah:chippingDelineation <https://purl.org/megalod/kos/ah-chippingDelineation/$delineationValue> ;\n";
                }
            }

            if (isset($values['ah:chippingLocationSide'])) {
                foreach ($values['ah:chippingLocationSide']['values'] as $value) {
                    $locationValue = strtolower($value->value());
                    $ttl .= "    ah:chippingLocationSide <https://purl.org/megalod/kos/ah-chippingLocation/$locationValue> ;\n";
                }
            }

            if (isset($values['ah:chippingLocationTransversal'])) {
                foreach ($values['ah:chippingLocationTransversal']['values'] as $value) {
                    $locationValue = strtolower($value->value());
                    $ttl .= "    ah:chippingLocationTransversal <https://purl.org/megalod/kos/ah-chippingLocation/$locationValue> ;\n";
                }
            }

            if (isset($values['ah:chippingShape'])) {
                foreach ($values['ah:chippingShape']['values'] as $value) {
                    $shapeValue = strtolower($value->value());
                    $ttl .= "    ah:chippingShape <https://purl.org/megalod/kos/ah-chippingShape/$shapeValue> ;\n";
                }
            }

            $ttl = rtrim($ttl, ";\n") . " .\n\n";
        }

        return $ttl;
    }

    /**
     * This method checks if the values contain coordinates data.
     * It looks for specific properties that indicate coordinates information.
     * @param mixed $values
     * @return bool True if coordinates data is present, false otherwise
     */
    private function hasCoordinatesData($values)
    {
        return isset($values['Coordinates']) || isset($values['excavation:hasCoordinatesInSquare']);
    }


    /**
     * This method processes the coordinates data and generates the TTL string.
     * It uses original URIs for coordinates properties.
     * It checks for the presence of coordinates data and constructs the appropriate URIs.
     * If coordinates data is present, it generates a coordinates object with the relevant properties.
     * @param mixed $values
     * @param mixed $arrowheadUri
     * @param mixed $identifier
     * @return string
     */
    private function processCoordinatesWithOriginalUris($values, $arrowheadUri, $identifier)
    {
        $ttl = "";
        $coordinateProperties = ['Coordinates', 'excavation:hasCoordinatesInSquare'];
        $axes = ['x', 'y', 'z'];
        $found = ['x' => null, 'y' => null, 'z' => null];

        foreach ($coordinateProperties as $property) {
            if (isset($values[$property])) {
                foreach ($values[$property]['values'] as $value) {
                    $coordString = $value->value();
                    if (preg_match_all('/([XYZ]):\s*([0-9.]+)/', $coordString, $matches, PREG_SET_ORDER)) {
                        $coordinatesUri = "$arrowheadUri/coordinates/$identifier-coordinates";
                        $ttl .= "\n# =========== COORDINATES IN SQUARE ===========\n\n";
                        $ttl .= "<$coordinatesUri> a excav:Coordinates ;\n";
                        foreach ($matches as $match) {
                            $axis = strtolower($match[1]);
                            $found[$axis] = $match[2];
                            $typometryUri = "$arrowheadUri/typometry/$identifier-$axis";
                            if ($axis === 'x') {
                                $ttl .= "    geo:long <$typometryUri> ;\n";
                            } elseif ($axis === 'y') {
                                $ttl .= "    geo:lat <$typometryUri> ;\n";
                            } else {
                                $ttl .= "    schema:depth <$typometryUri> ;\n";
                            }
                        }
                        $ttl = rtrim($ttl, ";\n") . " .\n\n";
                        // declare axis (X, Y, Z)
                        foreach ($axes as $axis) {
                            $typometryUri = "$arrowheadUri/typometry/$identifier-$axis";
                            $ttl .= "<$typometryUri> a excav:TypometryValue ;\n";
                            if ($found[$axis] !== null) {
                                $ttl .= "    schema:value \"{$found[$axis]}\"^^xsd:decimal ;\n";
                            } else {
                                $ttl .= "    schema:value \"\"^^xsd:decimal ;\n";
                            }
                            $ttl .= "    schema:UnitCode <http://qudt.org/vocab/unit/CMT> .\n\n";
                        }
                        break 2;
                    }
                }
            }
        }
        return $ttl;
    }

    /**
     * This method processes the GPS coordinates data and generates the TTL string.
     * It uses original URIs for GPS properties.
     * It checks for the presence of GPS coordinates data and constructs the appropriate URIs.
     * If GPS coordinates data is present, it generates a GPS object with the relevant properties.
     * @param mixed $values
     * @param mixed $originalBaseUri
     * @param mixed $identifier
     * @return string
     */
    private function processGPSWithOriginalUris($values, $originalBaseUri, $identifier)
    {
        $ttl = "";

        if (isset($values['excavation:hasGPSCoordinates'])) {
            foreach ($values['excavation:hasGPSCoordinates']['values'] as $value) {
                $gpsString = $value->value();

                // Parse GPS string "lat: _, Long:_"
                if (preg_match('/Lat:\s*([0-9.-]+),\s*Long:\s*([0-9.-]+)/', $gpsString, $matches)) {
                    $lat = $matches[1];
                    $long = $matches[2];

                    $excavationId = $this->extractExcavationId($values);
                    $gpsUri = "$originalBaseUri/excavation/$excavationId/gps/$identifier-gps";

                    $ttl .= "\n# =========== GPS COORDINATES ===========\n\n";
                    $ttl .= "<$gpsUri> a excav:GPSCoordinates ;\n";
                    $ttl .= "    geo:lat \"$lat\"^^xsd:decimal ;\n";
                    $ttl .= "    geo:long \"$long\"^^xsd:decimal .\n\n";
                }
            }
        }

        return $ttl;
    }

    /**
     * This method extracts the excavation ID from the values.
     * It looks for the excavation context reference and extracts the ID from the URI.
     * @param mixed $values The values from the resource
     * @return string The excavation ID or 'unknown' if not found
     */
    private function extractExcavationId($values)
    {
        $contextProperties = [
            'excavation:foundInLocation',
            'excavation:foundInSquare', 
            'excavation:foundInContext',
            'excavation:foundInSVU'
        ];

        foreach ($contextProperties as $property) {
            if (isset($values[$property])) {
                foreach ($values[$property]['values'] as $value) {
                    if ($value->uri()) {
                        $uri = $value->uri();
                        if (preg_match('/\/excavation\/([^\/]+)\//', $uri, $matches)) {
                            return $matches[1];
                        }
                    }
                }
            }
        }

        return 'unknown';
    }






    /**
     * This method escapes special characters in a string for use in TTL format.
     * It replaces quotes, backslashes, and control characters with their escaped versions.
     * @param string $string The string to escape
     * @return string The escaped string
     */
    private function escapeTtlString($string)
    {
        return str_replace(
            ['"', '\\', "\n", "\r", "\t"],
            ['\"', '\\\\', '\\n', '\\r', '\\t'],
            $string
        );
    }
}
