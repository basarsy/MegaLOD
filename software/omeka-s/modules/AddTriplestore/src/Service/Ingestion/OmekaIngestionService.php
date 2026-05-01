<?php

namespace AddTriplestore\Service\Ingestion;

/**
 * Transforms Turtle payloads into Omeka S API item payloads (extracted from IndexController).
 */
class OmekaIngestionService
{
    /** @var string */
    private $localBaseUri;

    /** @var int|null */
    private $contextItemSetId;

    /** @var OmekaResourceLookupService */
    private $resourceLookup;

    public function __construct(
        string $megalodLocalBaseUri,
        OmekaResourceLookupService $resourceLookup
    ) {
        $this->localBaseUri = rtrim($megalodLocalBaseUri, '/') . '/';
        $this->resourceLookup = $resourceLookup;
    }


    private function determineItemType($subjectType) {
        $typeMap = [
            'arrowhead' => 'Arrowhead',
            'item' => 'Archaeological Item', 
            'excavation' => 'Excavation',
            'context' => 'Context',
            'svu' => 'Stratigraphic Unit',
            'square' => 'Square',
            'unknown' => 'Archaeological Object'
        ];
        
        return $typeMap[$subjectType] ?? 'Archaeological Object';
    }

    private function extractAllMeasurements($rdfData, $subject, &$itemData, $currentItemSetId) {
       
        
        $measurementProperties = [
            'height' => [
                'uris' => [
                    'http://schema.org/height', 
                    'schema:height'
                ],
                'label' => 'Height',
                'propertyId' => 5616
            ],
            'width' => [
                'uris' => [
                    'http://schema.org/width', 
                    'schema:width'
                ],
                'label' => 'Width', 
                'propertyId' => 5688
            ],
            'depth' => [
                'uris' => [
                    'http://schema.org/depth', 
                    'schema:depth'
                ],
                'label' => 'Thickness',
                'propertyId' => 7244
            ],
            'weight' => [
                'uris' => [
                    'http://schema.org/weight', 
                    'schema:weight'
                ],
                'label' => 'Weight',
                'propertyId' => 5779
            ],
            'bodyLength' => [
                'uris' => [
                    'https://purl.org/megalod/ms/ah/hasBodyLength', 
                    'ah:hasBodyLength',
                    "{$this->localBaseUri}$currentItemSetId/ah/hasBodyLength" 
                ],
                'label' => 'Body Length',
                'propertyId' => 7678
            ],
            'baseLength' => [
                'uris' => [
                    'https://purl.org/megalod/ms/ah/hasBaseLength', 
                    'ah:hasBaseLength',
                    "{$this->localBaseUri}$currentItemSetId/ah/hasBaseLength" 
                ],
                'label' => 'Base Length', 
                'propertyId' => 7679
            ]
        ];
        
        foreach ($measurementProperties as $measurementName => $config) {
            $found = false;
            
            foreach ($config['uris'] as $uri) {
                if (isset($rdfData[$subject][$uri])) {
       
                    
                    foreach ($rdfData[$subject][$uri] as $measObj) {
                        if ($measObj['type'] === 'uri' && isset($rdfData[$measObj['value']])) {
                            $measurementUri = $measObj['value'];
       
                            
                            $value = $this->extractMeasurementValue($rdfData, $measurementUri);
                            $unit = $this->extractMeasurementUnit($rdfData, $measurementUri);
                            
                            if ($value !== null) {
                                $displayValue = $value;
                                if ($unit) {
                                    $cleanUnit = str_replace(['<', '>'], '', $unit);
                                    $displayValue .= " " . $cleanUnit;
                                }
                                
                                if (!isset($itemData[$config['label']])) {
                                    $itemData[$config['label']] = [];
                                }
                                
                                $itemData[$config['label']][] = [
                                    'type' => 'literal',
                                    'property_id' => $config['propertyId'],
                                    '@value' => $displayValue
                                ];
                                
       
                                $found = true;
                            }
                        }
                    }
                    break;
                }
            }
            
            if (!$found) {
       
            }
        }
    }


    /**
     * Extracts complete morphology data from the RDF data for a given subject.
     * This method looks for morphology-related properties and processes them.
     * @param mixed $rdfData The RDF data array
     * @param mixed $subject The subject URI to extract morphology data from
     * @param array &$itemData The item data array to populate with morphology properties
     * @param int|null $currentItemSetId The ID of the current item set, if available
     */

    private function extractArchaeologicalContext($rdfData, $subject, &$itemData, $currentItemSetId) {
       
        
        $contextProperties = [
            'location' => [
                'uris' => [
                    'https://purl.org/megalod/ms/excavation/foundInLocation',
                    'excav:foundInLocation',
                    "{$this->localBaseUri}$currentItemSetId/excavation/foundInLocation"
                ],
                'label' => 'Found in Location',
                'propertyId' => 7680,
                'type' => 'resource'
            ],
            'square' => [
                'uris' => [
                    'https://purl.org/megalod/ms/excavation/foundInSquare',
                    'excav:foundInSquare',
                    "{$this->localBaseUri}$currentItemSetId/excavation/foundInSquare"
                ],
                'label' => 'Found in Square',
                'propertyId' => 7683,
                'type' => 'resource'
            ],
            'context' => [
                'uris' => [
                    'https://purl.org/megalod/ms/excavation/foundInContext',
                    'excav:foundInContext',
                    "{$this->localBaseUri}$currentItemSetId/excavation/foundInContext"
                ],
                'label' => 'Found in Context',
                'propertyId' => 7672,
                'type' => 'resource'
            ],
            'foundInSVU' => [
                'uris' => [
                    'https://purl.org/megalod/ms/excavation/foundInSVU', 
                    'excav:foundInSVU',
                    "{$this->localBaseUri}$currentItemSetId/excavation/foundInSVU"
                ],
                'label' => 'Found in SVU',
                'propertyId' => 7671
            ],
        ];
        
        foreach ($contextProperties as $propName => $config) {
            foreach ($config['uris'] as $uri) {
                if (isset($rdfData[$subject][$uri])) {
       
                    
                    if (!isset($itemData[$config['label']])) {
                        $itemData[$config['label']] = [];
                    }
                    
                    foreach ($rdfData[$subject][$uri] as $contextObj) {
                        if ($contextObj['type'] === 'uri') {
                            $contextValue = $this->extractContextDisplayValue($rdfData, $contextObj['value']);
                            $displayValue = $contextValue ?: $contextObj['value'];
                            
                            
                            $itemData[$config['label']][] = [
                                'type' => 'uri',  
                                'property_id' => $config['propertyId'],
                                '@id' => $contextObj['value'],
                                'o:label' => $displayValue
                            ];
                            
       
                        }
                    }
                    break; 
                } else {
       
                }
            }
        }
    }

    /**
     * This method extracts encounter event data for an arrowhead item.
     * It looks for encounter events related to the arrowhead and processes them.
     * @param mixed $rdfData The RDF data array
     * @param mixed $subject The subject URI to extract encounter event data from
     * @param array &$itemData The item data array to populate with encounter properties
     * @param int|null $currentItemSetId The ID of the current item set, if available
     */

    private function extractArchaeologistData($rdfData, $archaeologistUri) {
        $data = [
            'name' => null,
            'orcid' => null,
            'email' => null
        ];
        
        if (isset($rdfData[$archaeologistUri]['http://xmlns.com/foaf/0.1/name'])) {
            foreach ($rdfData[$archaeologistUri]['http://xmlns.com/foaf/0.1/name'] as $nameObj) {
                if ($nameObj['type'] === 'literal') {
                    $data['name'] = $nameObj['value'];
                    break;
                }
            }
        }
        
        if (isset($rdfData[$archaeologistUri]['http://xmlns.com/foaf/0.1/account'])) {
            foreach ($rdfData[$archaeologistUri]['http://xmlns.com/foaf/0.1/account'] as $accountObj) {
                if ($accountObj['type'] === 'uri') {
                    $orcidUrl = $accountObj['value'];
                    if (strpos($orcidUrl, 'orcid.org') !== false) {
                        $parts = explode('/', $orcidUrl);
                        $data['orcid'] = end($parts);
                        break;
                    }
                }
            }
        }
        
        if (isset($rdfData[$archaeologistUri]['http://xmlns.com/foaf/0.1/mbox'])) {
            foreach ($rdfData[$archaeologistUri]['http://xmlns.com/foaf/0.1/mbox'] as $emailObj) {
                if ($emailObj['type'] === 'uri') {
                    $emailUrl = $emailObj['value'];
                    if (strpos($emailUrl, 'mailto:') === 0) {
                        $data['email'] = substr($emailUrl, 7);
                        break;
                    }
                }
            }
        }
        
        return ($data['name'] || $data['orcid']) ? $data : null;
    }




    /**
     * Processes SVU data from RDF and populates item data.
     * This method extracts SVU ID, description, and other relevant information from RDF data.
     * @param array $rdfData The RDF data containing SVU information
     * @param string $subject The subject URI to process
     * @param array &$itemData The item data to populate with extracted information
     */

    private function extractCommonProperties($rdfData, $subject, &$itemData) {
        // Map common predicates to Omeka S properties with correct labels
        $commonPropertyMap = [
            'http://dbpedia.org/ontology/Annotation' => ['Description', 4], // Use description instead
            'http://www.cidoc-crm.org/cidoc-crm/E3_Condition_State' => ['Condition State', 476],
            'http://www.cidoc-crm.org/cidoc-crm/E55_Type' => ['Type', 399],
        ];
        
        
        foreach ($commonPropertyMap as $predicate => $mapping) {
            if (isset($rdfData[$subject][$predicate])) {
                $term = $mapping[0];
                $propertyId = $mapping[1];
                
                if (!isset($itemData[$term])) {
                    $itemData[$term] = [];
                }
                
                foreach ($rdfData[$subject][$predicate] as $object) {
                    if ($object['type'] === 'literal') {
                        if ($object['value'] === 'true' || $object['value'] === 'false') {
                            $displayValue = ($object['value'] === 'true') ? 'True' : 'False';
                        } else {
                            $displayValue = $object['value'];
                        }
                        
                        $itemData[$term][] = [
                            'type' => 'literal',
                            'property_id' => $propertyId,
                            '@value' => $displayValue
                        ];
                    } elseif ($object['type'] === 'uri') {
                        if (strpos($object['value'], '/kos/') !== false) {
                            $parts = explode('/', $object['value']);
                            $value = end($parts);
                            
                            $itemData[$term][] = [
                                'type' => 'literal',
                                'property_id' => $propertyId,
                                '@value' => $value
                            ];
                        } else {
                            $itemData[$term][] = [
                                'type' => 'uri',
                                'property_id' => $propertyId,
                                '@id' => $object['value'],
                                'o:label' => $object['value']
                            ];
                        }
                    }
                }
            }
        }
    }


    /**
     * This method determines the item type based on the subject type.
     * @param string $subjectType The type of the subject (e.g., 'arrowhead', 'item', etc.)
     * @return string The corresponding item type for Omeka S
     */

    private function extractCompleteChippingData($rdfData, $subject, &$itemData, $currentItemSetId) {
       
        
        // First try to find chipping via hasChipping property
        $chippingUris = [
            'https://purl.org/megalod/ms/ah/hasChipping',
            'ah:hasChipping'
        ];
        
        if ($currentItemSetId) {
            $chippingUris[] = "{$this->localBaseUri}$currentItemSetId/ah/hasChipping";
        }
        
        $chippingFound = false;
        
        foreach ($chippingUris as $chippingUri) {
            if (isset($rdfData[$subject][$chippingUri])) {
                foreach ($rdfData[$subject][$chippingUri] as $chipObj) {
                    if ($chipObj['type'] === 'uri' && isset($rdfData[$chipObj['value']])) {
                        $this->processChippingResource($rdfData, $chipObj['value'], $itemData);
                        $chippingFound = true;
                    }
                }
            }
        }
        
        if (!$chippingFound) {
       
            
            foreach ($rdfData as $resourceUri => $properties) {
                if (isset($properties['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'])) {
                    foreach ($properties['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] as $typeObj) {
                        if ($typeObj['type'] === 'uri' && 
                            (strpos($typeObj['value'], 'Chipping') !== false ||
                             $typeObj['value'] === 'https://purl.org/megalod/ms/ah/Chipping' ||
                             $typeObj['value'] === 'ah:Chipping')) {
                            
       
                            $this->processChippingResource($rdfData, $resourceUri, $itemData);
                            $chippingFound = true;
                        }
                    }
                }
            }
        }
        
        if (!$chippingFound) {
       
        }
    }

    /**
     * Extracts coordinate data from the RDF data for a given subject.
     * This method looks for coordinate-related properties and processes them.
     * @param mixed $rdfData The RDF data array
     * @param mixed $subject The subject URI to extract coordinate data from
     * @param array &$itemData The item data array to populate with coordinate properties
     * @param int|null $currentItemSetId The ID of the current item set, if available
     */

    private function extractCompleteMorphologyData($rdfData, $subject, &$itemData, $currentItemSetId) {
       
        
        $morphologyUris = [
            'https://purl.org/megalod/ms/ah/hasMorphology',
            'ah:hasMorphology'
        ];
        
        if ($currentItemSetId) {
            $morphologyUris[] = "{$this->localBaseUri}$currentItemSetId/ah/hasMorphology";
        }
        
        $morphologyFound = false;
        
        // Find via hasMorphology property
        foreach ($morphologyUris as $morphologyUri) {
            if (isset($rdfData[$subject][$morphologyUri])) {
                foreach ($rdfData[$subject][$morphologyUri] as $morphObj) {
                    if ($morphObj['type'] === 'uri' && isset($rdfData[$morphObj['value']])) {
                        $this->processMorphologyResource($rdfData, $morphObj['value'], $itemData);
                        $morphologyFound = true;
                    }
                }
            }
        }
        
        // Scan ALL resources for morphology types
        if (!$morphologyFound) {
       
            
            foreach ($rdfData as $resourceUri => $properties) {
                if (isset($properties['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'])) {
                    foreach ($properties['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] as $typeObj) {
                        if ($typeObj['type'] === 'uri' && 
                            (strpos($typeObj['value'], 'Morphology') !== false ||
                             $typeObj['value'] === 'https://purl.org/megalod/ms/ah/Morphology' ||
                             $typeObj['value'] === 'ah:Morphology')) {
                            
       
                            $this->processMorphologyResource($rdfData, $resourceUri, $itemData);
                            $morphologyFound = true;
                        }
                    }
                }
            }
        }
        
        if (!$morphologyFound) {
       
        }
    }

    /**
     * Extracts complete chipping data from the RDF data for a given subject.
     * This method looks for chipping-related properties and processes them.
     * @param mixed $rdfData The RDF data array
     * @param mixed $subject The subject URI to extract chipping data from
     * @param array &$itemData The item data array to populate with chipping properties
     * @param int|null $currentItemSetId The ID of the current item set, if available
     */

    private function extractContextDescription($rdfData, $contextUri) {
        if (isset($rdfData[$contextUri]['http://purl.org/dc/terms/description'])) {
            foreach ($rdfData[$contextUri]['http://purl.org/dc/terms/description'] as $descObj) {
                if ($descObj['type'] === 'literal') {
                    return $descObj['value'];
                }
            }
        }
        return null;
    }


    /**
     * Extract the square coordinates from the RDF data.
     * @param mixed $rdfData
     * @param mixed $squareUri
     */

    private function extractContextDisplayValue($rdfData, $contextUri) {
       
        
        if (isset($rdfData[$contextUri])) {
            $resource = $rdfData[$contextUri];
            
            if (isset($resource['http://purl.org/dc/terms/identifier'])) {
                foreach ($resource['http://purl.org/dc/terms/identifier'] as $idObj) {
                    if ($idObj['type'] === 'literal') {
       
                        return $idObj['value'];
                    }
                }
            }
            
            if (isset($resource['http://dbpedia.org/ontology/informationName'])) {
                foreach ($resource['http://dbpedia.org/ontology/informationName'] as $nameObj) {
                    if ($nameObj['type'] === 'literal') {
       
                        return $nameObj['value'];
                    }
                }
            }
            
            if (isset($resource['http://www.w3.org/2000/01/rdf-schema#label'])) {
                foreach ($resource['http://www.w3.org/2000/01/rdf-schema#label'] as $labelObj) {
                    if ($labelObj['type'] === 'literal') {
       
                        return $labelObj['value'];
                    }
                }
            }
        }
        
        $uriValue = $this->extractIdentifierFromUriStructure($contextUri);
        if ($uriValue) {
       
            return $uriValue;
        }
        
        if (strpos($contextUri, '/location/') !== false) {
            return 'Excavation Location';
        }
        
        if (preg_match('/\/(\d+)$/', $contextUri, $matches)) {
       
            return $matches[1]; 
        }
        
       
        return null;
    }

    /**
     * Extracts coordinate data from the RDF data for a given subject.
     * This method enhances the extraction by looking for multiple coordinate properties.
     * @param mixed $rdfData The RDF data array
     * @param mixed $subject The subject URI to extract coordinate data from
     * @param array &$itemData The item data array to populate with coordinate properties
     * @param int|null $currentItemSetId The ID of the current item set, if available
     */

    private function extractCoordinateData($rdfData, $subject, &$itemData, $currentItemSetId) {
       
        
        $coordinateUris = [
            'https://purl.org/megalod/ms/excavation/hasCoordinatesInSquare',
            'excav:hasCoordinatesInSquare'
        ];
        
        if ($currentItemSetId) {
            $coordinateUris[] = "{$this->localBaseUri}$currentItemSetId/excavation/hasCoordinatesInSquare";
        }
        
        $coordinatesFound = false;
        
        foreach ($coordinateUris as $coordinateUri) {
            if (isset($rdfData[$subject][$coordinateUri])) {
                foreach ($rdfData[$subject][$coordinateUri] as $coordObj) {
                    if ($coordObj['type'] === 'uri' && isset($rdfData[$coordObj['value']])) {
                        $this->processCoordinateResource($rdfData, $coordObj['value'], $itemData);
                        $coordinatesFound = true;
                    }
                }
            }
        }
        
        if (!$coordinatesFound) {
       
            
            foreach ($rdfData as $resourceUri => $properties) {
                if (isset($properties['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'])) {
                    foreach ($properties['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] as $typeObj) {
                        if ($typeObj['type'] === 'uri' && 
                            (strpos($typeObj['value'], 'Coordinates') !== false ||
                             $typeObj['value'] === 'https://purl.org/megalod/ms/excavation/Coordinates' ||
                             $typeObj['value'] === 'excav:Coordinates')) {
                            
       
                            $this->processCoordinateResource($rdfData, $resourceUri, $itemData);
                            $coordinatesFound = true;
                        }
                    }
                }
            }
        }
        
        if (!$coordinatesFound) {
       
        }
    }



    /**
     * Processes coordinate data for an arrowhead item.
     * This method extracts coordinates from the RDF data and formats them for display.
     * @param mixed $rdfData The RDF data array
     * @param mixed $coordinateUri The URI of the coordinate resource
     * @param array &$itemData The item data array to populate with coordinate properties
     */

    private function extractCoordinateDataEnhanced($rdfData, $subject, &$itemData, $currentItemSetId) {
       
        
        $this->extractCoordinateData($rdfData, $subject, $itemData, $currentItemSetId);

    }



    /**
     * Extracts media resources from the RDF data for a given subject.
     * This method looks for web resources and populates the item data with them.
     * @param mixed $rdfData The RDF data array
     * @param mixed $subject The subject URI to extract media resources from
     * @param array &$itemData The item data array to populate with media resources
     */

    private function extractDirectArrowheadProperties($rdfData, $subject, &$itemData, $currentItemSetId) {
       
        
        $propertyVariations = [
            'shape' => [
                'https://purl.org/megalod/ms/ah/shape',
                'ah:shape',
                "{$this->localBaseUri}$currentItemSetId/ah/shape" 
            ],
            'variant' => [
                'https://purl.org/megalod/ms/ah/variant', 
                'ah:variant',
                "{$this->localBaseUri}$currentItemSetId/ah/variant" 
            ],
            'material' => [
                'http://www.cidoc-crm.org/cidoc-crm/E57_Material',
                'crm:E57_Material' 
            ],
            'elongationIndex' => [
                'https://purl.org/megalod/ms/excavation/elongationIndex',
                'excav:elongationIndex' 
            ],
            'thicknessIndex' => [
                'https://purl.org/megalod/ms/excavation/thicknessIndex',
                'excav:thicknessIndex'
            ]
        ];
        
        $propertyMappings = [
            'shape' => ['Arrowhead Shape', 7651],
            'variant' => ['Arrowhead Variant', 7652], 
            'material' => ['Material', 4633],
            'elongationIndex' => ['Elongation Index', 7676],
            'thicknessIndex' => ['Thickness Index', 7677]
        ];
        
        foreach ($propertyVariations as $propertyName => $uriVariations) {
            $found = false;
            
            foreach ($uriVariations as $uri) {
                if (isset($rdfData[$subject][$uri])) {
                    $label = $propertyMappings[$propertyName][0];
                    $propertyId = $propertyMappings[$propertyName][1];
                    
                    if (!isset($itemData[$label])) {
                        $itemData[$label] = [];
                    }
                    
                    foreach ($rdfData[$subject][$uri] as $valueObj) {
                        $value = $this->extractPropertyValue($valueObj);
                        if ($value) {
                            $itemData[$label][] = [
                                'type' => 'literal',
                                'property_id' => $propertyId,
                                '@value' => $value
                            ];
                            
       
                            $found = true;
                        }
                    }
                    break; 
                }
            }
        }
    }


    /**
     * Processes morphology data for an arrowhead item.
     * This method extracts properties related to the morphology of the arrowhead.
     * @param mixed $rdfData The RDF data array
     * @param mixed $morphologyUri The URI of the morphology resource
     * @param array &$itemData The item data array to populate with morphology properties
     */

    private function extractEncounterEventData($rdfData, $subject, &$itemData, $currentItemSetId) {
       
        
        $encounterUris = [
            'https://cidoc-crm.org/extensions/crmsci/O19i_was_object_encountered_through',
            'crmsci:O19i_was_object_encountered_through'
        ];
        
        if ($currentItemSetId) {
            $encounterUris[] = "{$this->localBaseUri}$currentItemSetId/crmsci/O19i_was_object_encountered_through";
        }
        
        $encounterEventUri = null;
        
        foreach ($encounterUris as $uri) {
            if (isset($rdfData[$subject][$uri])) {
                foreach ($rdfData[$subject][$uri] as $encounterObj) {
                    if ($encounterObj['type'] === 'uri') {
                        $encounterEventUri = $encounterObj['value'];
       
                        break 2;
                    }
                }
            }
        }
        
        if (!$encounterEventUri) {
       
            
            foreach ($encounterUris as $uri) {
                if (isset($rdfData[$subject][$uri])) {
                    foreach ($rdfData[$subject][$uri] as $encounterObj) {
                        if ($encounterObj['type'] === 'uri') {
                            $encounterEventUri = $encounterObj['value'];
                            
                            if (!isset($itemData['Encounter Event'])) {
                                $itemData['Encounter Event'] = [];
                            }
                            
                            $itemData['Encounter Event'][] = [
                                'type' => 'uri',
                                'property_id' => 7686,
                                '@id' => $encounterEventUri,
                                'o:label' => 'Archaeological Encounter Event'
                            ];
                            
                            if (isset($rdfData[$encounterEventUri])) {
                                $this->processEncounterEvent($rdfData, $encounterEventUri, $itemData, $currentItemSetId);
                            }
                        }
                    }
                }
            }
        }
        
        // Process the encounter event if found
        if ($encounterEventUri && isset($rdfData[$encounterEventUri])) {
            $this->processEncounterEvent($rdfData, $encounterEventUri, $itemData, $this->getCurrentItemSetContext());
        } else {
       
        }
    }


    /**
     * Extracts the display value for a context resource.
     * This method tries multiple strategies to find a meaningful display value for the context.
     * @param mixed $rdfData The RDF data array
     * @param mixed $contextUri The URI of the context resource
     * @return string|null The display value or null if not found
     */

    private function extractGPSCoordinates($rdfData, $subject, &$itemData, $currentItemSetId) {
        $gpsPropertyUris = [
            'https://purl.org/megalod/ms/excavation/hasGPSCoordinates',
            'excav:hasGPSCoordinates'
        ];
        
        if ($currentItemSetId) {
            $gpsPropertyUris[] = "{$this->localBaseUri}$currentItemSetId/excavation/hasGPSCoordinates";
        }
        
        foreach ($gpsPropertyUris as $gpsPropertyUri) {
            if (isset($rdfData[$subject][$gpsPropertyUri])) {
                foreach ($rdfData[$subject][$gpsPropertyUri] as $gpsObj) {
                    if ($gpsObj['type'] === 'uri' && isset($rdfData[$gpsObj['value']])) {
                        $gpsUri = $gpsObj['value'];
                        $coordinates = [];
                        
                        if (isset($rdfData[$gpsUri]['http://www.w3.org/2003/01/geo/wgs84_pos#lat'])) {
                            $coordinates['lat'] = $rdfData[$gpsUri]['http://www.w3.org/2003/01/geo/wgs84_pos#lat'][0]['value'];
                        }
                        
                        if (isset($rdfData[$gpsUri]['http://www.w3.org/2003/01/geo/wgs84_pos#long'])) {
                            $coordinates['long'] = $rdfData[$gpsUri]['http://www.w3.org/2003/01/geo/wgs84_pos#long'][0]['value'];
                        }
                        
                        if (!empty($coordinates)) {
                            $coordStr = "Lat: {$coordinates['lat']}, Long: {$coordinates['long']}";
                            
                            $itemData['GPS Coordinates'][] = [
                                'type' => 'literal',
                                'property_id' => 7664,
                                '@value' => $coordStr
                            ];
                            
       
                            return;
                        }
                    }
                }
            }
        }
    }



    /**
     * Extracts direct arrowhead properties from the RDF data.
     * This method retrieves properties like shape, variant, material, elongation index, and thickness index.
     * @param mixed $rdfData The RDF data array
     * @param mixed $subject The subject URI of the arrowhead
     * @param array &$itemData The item data array to populate with extracted properties
     * @param int|null $currentItemSetId The ID of the current item set, if available
     */

    private function extractIdentifier($rdfData, $subject) {
        if (isset($rdfData[$subject]['http://purl.org/dc/terms/identifier'])) {
            foreach ($rdfData[$subject]['http://purl.org/dc/terms/identifier'] as $idObj) {
                if ($idObj['type'] === 'literal') {
                    return $idObj['value'];
                }
            }
        }
        return null;
    }



    /**
     * Processes the RDF data for an arrowhead item.
     * This method extracts various properties and measurements related to the arrowhead.
     * @param mixed $rdfData The RDF data array
     * @param mixed $subject The subject URI of the arrowhead
     * @param array &$itemData The item data array to populate with extracted information
     */

    private function extractIdentifierFromUri($uri) {
        $parts = explode('/', $uri);
        return end($parts);
    }


    /**
     * Extracts SVU data from RDF data.
     * This method retrieves the name and description of the SVU from the RDF data.
     * @param array $rdfData The RDF data containing SVU information
     * @param string $svuUri The URI of the SVU to extract data from
     * @return array|null An associative array with 'name' and 'description', or null if not found
     */

    private function extractIdentifierFromUriStructure($resourceUri) {
       
        
        if (preg_match('/\/svu\/([^\/]+)$/', $resourceUri, $matches)) {
       
            return $matches[1];
        }
        
        // Pattern for context URIs: extract the last segment after /context/
        if (preg_match('/\/context\/([^\/]+)$/', $resourceUri, $matches)) {
       
            return $matches[1];
        }
        
        if (preg_match('/\/square\/([^\/]+)$/', $resourceUri, $matches)) {
       
            return $matches[1];
        }
        
       
        if (preg_match('/\/([^\/]+)\/item-(\d+)$/', $resourceUri, $matches)) {
            $resourceType = $matches[1]; 
            $itemId = $matches[2];       
            
       
            
            $realIdentifier = $this->resourceLookup->getRealIdentifierFromOmekaItem($itemId);
            if ($realIdentifier) {
       
                return $realIdentifier;
            }
            
            switch (strtolower($resourceType)) {
                case 'context':
                    return "CTX-" . str_pad($itemId % 1000, 3, '0', STR_PAD_LEFT);
                case 'svu':
                    return "Layer-" . str_pad($itemId % 100, 2, '0', STR_PAD_LEFT); 
                case 'square':
                    $letterIndex = ($itemId - 1) % 26;
                    $letter = chr(65 + $letterIndex); 
                    $number = floor(($itemId - 1) / 26) + 1;
                    return $letter . $number; 
                default:
                    return $resourceType . "-" . ($itemId % 1000);
            }
        }
        

        if (preg_match('/\/([^\/]+)\/([^\/]+)$/', $resourceUri, $matches)) {
            $resourceType = $matches[1];
            $identifier = $matches[2];
            
            if (!preg_match('/^item-\d+$/', $identifier)) {
       
                return $identifier;
            }
        }
        
        $parts = explode('/', $resourceUri);
        $lastPart = end($parts);
        
        if (preg_match('/^[A-Za-z0-9-]+$/', $lastPart) && strlen($lastPart) > 1 && !preg_match('/^\d+$/', $lastPart)) {
       
            return $lastPart;
        }
        
        return null;
    }


    /**
     * this method retrieves the real identifier from an Omeka item.
     * @param mixed $itemId
     */

    private function extractMeasurementUnit($rdfData, $typometryUri) {
       
        
        if (!isset($rdfData[$typometryUri])) {
            return null;
        }
        
        if (isset($rdfData[$typometryUri]['http://schema.org/UnitCode'])) {
            foreach ($rdfData[$typometryUri]['http://schema.org/UnitCode'] as $unitObj) {
                if ($unitObj['type'] === 'literal') {
       
                    return $unitObj['value'];
                } elseif ($unitObj['type'] === 'uri') {
                    $parts = explode('/', $unitObj['value']);
                    $unit = end($parts);
       
                    return $unit;
                }
            }
        }
        
        $alternativeUnitProps = [
            'http://schema.org/unitCode',
            'http://purl.org/dc/terms/format',
            'http://qudt.org/schema/qudt#unit',
            'http://qudt.org/schema/qudt#hasUnit'
        ];
        
        foreach ($alternativeUnitProps as $unitProp) {
            if (isset($rdfData[$typometryUri][$unitProp])) {
                foreach ($rdfData[$typometryUri][$unitProp] as $unitObj) {
                    if ($unitObj['type'] === 'literal') {
       
                        return $unitObj['value'];
                    } elseif ($unitObj['type'] === 'uri') {
                        $parts = explode('/', $unitObj['value']);
                        $unit = end($parts);
       
                        return $unit;
                    }
                }
            }
        }
        
        if (isset($rdfData[$typometryUri]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'])) {
            foreach ($rdfData[$typometryUri]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] as $typeObj) {
                if ($typeObj['value'] === 'https://purl.org/megalod/ms/excavation/Weight') {
                    $value = $this->extractMeasurementValue($rdfData, $typometryUri);
                    if ($value !== null) {
                        if (preg_match('/\s([a-zA-Z]+)/', $value, $matches)) {
                            $unit = $matches[1];
       
                            return $unit;
                        } else {
       
                            return null; 
                        }
                    }
                }
            }
        }
        
       
        return null;
    }


    /**
     * Extracts a meaningful identifier from RDF data for a given resource URI.
     * This method attempts to find an identifier in the RDF data, falling back to URI structure if necessary.
     * @param array $rdfData The RDF data containing resource information
     * @param string $resourceUri The URI of the resource to extract the identifier from
     * @return string|null The extracted identifier or null if not found
     */

    private function extractMeasurementValue($rdfData, $typometryUri) {
       
        
        if (!isset($rdfData[$typometryUri])) {
       
            return null;
        }
        
       
        
        if (isset($rdfData[$typometryUri]['http://schema.org/value'])) {
            foreach ($rdfData[$typometryUri]['http://schema.org/value'] as $valueObj) {
                if ($valueObj['type'] === 'literal') {
       
                    return $valueObj['value'];
                }
            }
        }
        
        $alternativeValueProps = [
            'http://www.w3.org/1999/02/22-rdf-syntax-ns#value',
            'http://purl.org/dc/terms/extent',
            'http://qudt.org/schema/qudt#numericValue'
        ];
        
        foreach ($alternativeValueProps as $valueProp) {
            if (isset($rdfData[$typometryUri][$valueProp])) {
                foreach ($rdfData[$typometryUri][$valueProp] as $valueObj) {
                    if ($valueObj['type'] === 'literal') {
       
                        return $valueObj['value'];
                    }
                }
            }
        }
        
       
        return null;
    }


    /**
     * Extracts the measurement unit from RDF data.
     * This method retrieves the unit of a typometry measurement from the RDF data.
     * @param array $rdfData The RDF data containing typometry information
     * @param string $typometryUri The URI of the typometry measurement to extract
     * @return string|null The extracted measurement unit or null if not found
     */

    private function extractMediaResources($rdfData, $subject, &$itemData) {
       
        
        $mediaUris = [
            'http://www.europeana.eu/schemas/edm/Webresource',
            'edm:Webresource'
        ];
        
        foreach ($mediaUris as $uri) {
            if (isset($rdfData[$subject][$uri])) {
                if (!isset($itemData['Web Resources'])) {
                    $itemData['Web Resources'] = [];
                }
                
                foreach ($rdfData[$subject][$uri] as $mediaObj) {
                    if ($mediaObj['type'] === 'uri') {
                        $itemData['Web Resources'][] = [
                            'type' => 'uri',
                            'property_id' => 38, 
                            '@id' => $mediaObj['value'],
                            'o:label' => basename($mediaObj['value'])
                        ];
                        
       
                    }
                }
                break;
            }
        }
    }


    /**
     * Retrieves the current item set context.
     * This method should return the ID of the current item set being processed.
     * @return int|null The current item set ID or null if not set
     */

    private function extractPropertyValue($valueObj, $type = 'auto') {
        if ($valueObj['type'] === 'literal') {
            $value = $valueObj['value'];
            
            if ($type === 'boolean' || $value === 'true' || $value === 'false') {
                if ($value === 'true') {
                    switch ($type) {
                        case 'morphology_point':
                            return 'Sharp';
                        case 'morphology_body':
                            return 'Symmetrical';
                        case 'chipping_amplitude':
                            return 'Marginal';
                        case 'chipping_orientation':
                            return 'Lateral';
                        default:
                            return 'True';
                    }
                } elseif ($value === 'false') {
                    switch ($type) {
                        case 'morphology_point':
                            return 'Fractured';
                        case 'morphology_body':
                            return 'Non-symmetrical';
                        case 'chipping_amplitude':
                            return 'Deep';
                        case 'chipping_orientation':
                            return 'Transverse';
                        default:
                            return 'False';
                    }
                }
            }
            
            return $value;
        } elseif ($valueObj['type'] === 'uri') {
            if (strpos($valueObj['value'], '/kos/') !== false || 
                strpos($valueObj['value'], '/ah-') !== false ||
                strpos($valueObj['value'], '/MegaLOD-') !== false) {
                $parts = explode('/', $valueObj['value']);
                $lastPart = end($parts);
                
                if (strpos($lastPart, 'ah-') === 0) {
                    $clean = substr($lastPart, 3); 
                } elseif (strpos($lastPart, 'MegaLOD-Index') === 0) {
                    $clean = str_replace('MegaLOD-Index', '', $lastPart);
                } else {
                    $clean = $lastPart;
                }
                
                return str_replace('-', ' ', $clean);
            } else {
                $parts = explode('/', $valueObj['value']);
                return end($parts);
            }
        }
        
        return null;
    }

    /**
     * Extracts all measurements from the RDF data for a given subject.
     * This method looks for specific measurement properties and retrieves their values and units.
     * @param mixed $rdfData The RDF data array
     * @param mixed $subject The subject URI to extract measurements from
     * @param array &$itemData The item data array to populate with measurement properties
     * @param int|null $currentItemSetId The ID of the current item set, if available
     */

    private function extractResourceDisplayName($rdfData, $resourceUri) {
        if (isset($rdfData[$resourceUri]['http://purl.org/dc/terms/identifier'])) {
            foreach ($rdfData[$resourceUri]['http://purl.org/dc/terms/identifier'] as $idObj) {
                if ($idObj['type'] === 'literal') {
                    return $idObj['value'];
                }
            }
        }
        
        if (isset($rdfData[$resourceUri]['http://purl.org/dc/terms/title'])) {
            foreach ($rdfData[$resourceUri]['http://purl.org/dc/terms/title'] as $titleObj) {
                if ($titleObj['type'] === 'literal') {
                    return $titleObj['value'];
                }
            }
        }
        
        return basename($resourceUri);
    }

    /**
     * This method extracts the display value for a context resource.
     * @param mixed $rdfData
     * @param mixed $subject
     * @param mixed $itemData
     * @param mixed $currentItemSetId
     * @return void
     */

    private function extractResourceIdentifier($rdfData, $resourceUri) {
       
       
        
        if (!isset($rdfData[$resourceUri])) {
       
            
            $identifier = $this->extractIdentifierFromUriStructure($resourceUri);
            if ($identifier) {
       
                return $identifier;
            }
            
            return null;
        }
        
        $identifierPredicates = [
            'http://purl.org/dc/terms/identifier',
            'dct:identifier',
            'dcterms:identifier'
        ];
        
        foreach ($identifierPredicates as $predicate) {
            if (isset($rdfData[$resourceUri][$predicate])) {
                foreach ($rdfData[$resourceUri][$predicate] as $idObj) {
                    if ($idObj['type'] === 'literal') {
       
                        return $idObj['value'];
                    }
                }
            }
        }
        
        $identifier = $this->extractIdentifierFromUriStructure($resourceUri);
        if ($identifier) {
       
            return $identifier;
        }
        
       
        return null;
    }
    /**
     * Retrieves the  location URI from an excavation item set.
     * This method checks if the excavation has a valid location and returns its URI.
     * If no valid location is found, it constructs a fallback URI based on the excavation identifier.
     * @param int $itemSetId The ID of the item set to check
     * @return string|null The real location URI or null if not found
     */

    private function extractSquareCoordinates($rdfData, $squareUri) {
        $coords = [];
        
        if (isset($rdfData[$squareUri]['http://www.w3.org/2003/01/geo/wgs84_pos#lat'])) {
            foreach ($rdfData[$squareUri]['http://www.w3.org/2003/01/geo/wgs84_pos#lat'] as $latObj) {
                if ($latObj['type'] === 'literal') {
                    $coords[] = 'Lat: ' . $latObj['value'];
                }
            }
        }
        
        if (isset($rdfData[$squareUri]['http://www.w3.org/2003/01/geo/wgs84_pos#long'])) {
            foreach ($rdfData[$squareUri]['http://www.w3.org/2003/01/geo/wgs84_pos#long'] as $longObj) {
                if ($longObj['type'] === 'literal') {
                    $coords[] = 'Long: ' . $longObj['value'];
                }
            }
        }
        
        return !empty($coords) ? implode(', ', $coords) : null;
    }


    /**
     * Extract the square coordinates from the RDF data.
     * @param mixed $rdfData
     * @param mixed $subject
     * @param mixed $itemData
     * @return void
     */

    private function extractSvuData($rdfData, $svuUri) {
        $data = [
            'name' => null,
            'description' => null
        ];
        
        // Extract identifier as name
        if (isset($rdfData[$svuUri]['http://purl.org/dc/terms/identifier'])) {
            foreach ($rdfData[$svuUri]['http://purl.org/dc/terms/identifier'] as $idObj) {
                if ($idObj['type'] === 'literal') {
                    $data['name'] = $idObj['value'];
                    break;
                }
            }
        }
        
        // Extract description
        if (isset($rdfData[$svuUri]['http://purl.org/dc/terms/description'])) {
            foreach ($rdfData[$svuUri]['http://purl.org/dc/terms/description'] as $descObj) {
                if ($descObj['type'] === 'literal') {
                    $data['description'] = $descObj['value'];
                    break;
                }
            }
        }
        
        return ($data['name'] || $data['description']) ? $data : null;
    }



    /**
     * Extracts the measurement value from RDF data.
     * This method retrieves the value of a typometry measurement from the RDF data.
     * @param array $rdfData The RDF data containing typometry information
     * @param string $typometryUri The URI of the typometry measurement to extract
     * @return string|null The extracted measurement value or null if not found
     */

    private function extractTimelineRange($rdfData, $timelineUri) {
        if (!isset($rdfData[$timelineUri])) {
            return null;
        }
        
        $beginningYear = null;
        $beginningBC = null;
        $endYear = null;
        $endBC = null;
        
        $currentItemSetId = $this->getCurrentItemSetContext();
        
        // Extract beginning
        if (isset($rdfData[$timelineUri]['http://www.w3.org/2006/time#hasBeginning'])) {
            foreach ($rdfData[$timelineUri]['http://www.w3.org/2006/time#hasBeginning'] as $beginObj) {
                if ($beginObj['type'] === 'uri' && isset($rdfData[$beginObj['value']])) {
                    $beginUri = $beginObj['value'];
                    
                    // Extract year
                    if (isset($rdfData[$beginUri]['http://www.w3.org/2006/time#inXSDgYear'])) {
                        foreach ($rdfData[$beginUri]['http://www.w3.org/2006/time#inXSDgYear'] as $yearObj) {
                            if ($yearObj['type'] === 'literal') {
                                $beginningYear = abs((int)$yearObj['value']); 
                            }
                        }
                    }
                    
                    // Extract BC/AD with normalized URIs
                    $bcadPropertyUris = [
                        'https://purl.org/megalod/ms/excavation/bcad'
                    ];
                    if ($currentItemSetId) {
                        $bcadPropertyUris[] = "{$this->localBaseUri}$currentItemSetId/excavation/bcad";
                    }
                    
                    foreach ($bcadPropertyUris as $bcadPropertyUri) {
                        if (isset($rdfData[$beginUri][$bcadPropertyUri])) {
                            foreach ($rdfData[$beginUri][$bcadPropertyUri] as $bcObj) {
                                if ($bcObj['type'] === 'uri') {
                                    $parts = explode('/', $bcObj['value']);
                                    $bcacValue = end($parts);
                                    $beginningBC = ($bcacValue === 'BC');
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        if (isset($rdfData[$timelineUri]['http://www.w3.org/2006/time#hasEnd'])) {
            foreach ($rdfData[$timelineUri]['http://www.w3.org/2006/time#hasEnd'] as $endObj) {
                if ($endObj['type'] === 'uri' && isset($rdfData[$endObj['value']])) {
                    $endUri = $endObj['value'];
                    
                    if (isset($rdfData[$endUri]['http://www.w3.org/2006/time#inXSDgYear'])) {
                        foreach ($rdfData[$endUri]['http://www.w3.org/2006/time#inXSDgYear'] as $yearObj) {
                            if ($yearObj['type'] === 'literal') {
                                $endYear = abs((int)$yearObj['value']); 
                            }
                        }
                    }
                    
                    $bcadPropertyUris = [
                        'https://purl.org/megalod/ms/excavation/bcad'
                    ];
                    if ($currentItemSetId) {
                        $bcadPropertyUris[] = "{$this->localBaseUri}$currentItemSetId/excavation/bcad";
                    }
                    
                    foreach ($bcadPropertyUris as $bcadPropertyUri) {
                        if (isset($rdfData[$endUri][$bcadPropertyUri])) {
                            foreach ($rdfData[$endUri][$bcadPropertyUri] as $bcObj) {
                                if ($bcObj['type'] === 'uri') {
                                    $parts = explode('/', $bcObj['value']);
                                    $bcacValue = end($parts);
                                    $endBC = ($bcacValue === 'BC');
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        if ($beginningYear && $endYear) {
            $beginText = $beginningYear . ($beginningBC ? ' BC' : ' AD');
            $endText = $endYear . ($endBC ? ' BC' : ' AD');
            return "$beginText - $endText";
        } else if ($beginningYear) {
            $beginText = $beginningYear . ($beginningBC ? ' BC' : ' AD');
            return "From $beginText";
        } else if ($endYear) {
            $endText = $endYear . ($endBC ? ' BC' : ' AD');
            return "Until $endText";
        }
        
        return null;
    }




    /**
     * This method extracts common properties from RDF data and populates item data.
     * @param mixed $rdfData
     * @param mixed $subject
     * @param mixed $itemData
     * @return void
     */

    private function getCurrentItemSetContext(): ?int
    {
        return $this->contextItemSetId;
    }

    private function identifyMainSubjects($rdfData, $itemSetId = null) {
        $subjects = [];
        
       
        $mainSubjectTypes = [
            'https://purl.org/megalod/ms/ah/Arrowhead' => 'arrowhead',
            'https://purl.org/megalod/ms/excavation/Item' => 'item',
            'https://purl.org/megalod/ms/excavation/Excavation' => 'excavation',
            'https://purl.org/megalod/ms/excavation/Context' => 'context',
            'https://purl.org/megalod/ms/excavation/StratigraphicVolumeUnit' => 'svu',
            'https://purl.org/megalod/ms/excavation/Square' => 'square',
            'excav:Excavation' => 'excavation',
            'excav:Context' => 'context',
            'ah:Arrowhead' => 'arrowhead',
            'excav:Item' => 'item',
            'excav:StratigraphicVolumeUnit' => 'svu',
            'excav:Square' => 'square',
        ];
        
        if ($itemSetId) {
            $normalizedPatterns = [
                "{$this->localBaseUri}$itemSetId/ah/Arrowhead" => 'arrowhead',
                "{$this->localBaseUri}$itemSetId/excavation/Item" => 'item', 
                "{$this->localBaseUri}$itemSetId/excavation/Excavation" => 'excavation',
                "{$this->localBaseUri}$itemSetId/excavation/Context" => 'context',
                "{$this->localBaseUri}$itemSetId/excavation/StratigraphicVolumeUnit" => 'svu',
                "{$this->localBaseUri}$itemSetId/excavation/Square" => 'square',
            ];
            
            $mainSubjectTypes = array_merge($mainSubjectTypes, $normalizedPatterns);
            
       
        }
        
        $excludedTypes = [
            'https://purl.org/megalod/ms/excavation/Location',
            'https://purl.org/megalod/ms/excavation/GPSCoordinates',
            'http://www.ontologydesignpatterns.org/ont/dul/DUL.owl#Location',
            'http://dbpedia.org/ontology/Location',
            'excav:Location',
            'excav:GPSCoordinates',
            'https://purl.org/megalod/ms/excavation/Archaeologist',
            'excav:Archaeologist',
            'https://purl.org/megalod/ms/excavation/TimeLine',
            'https://purl.org/megalod/ms/excavation/Instant',
            'excav:TimeLine',
            'excav:Instant',
            'http://dbpedia.org/ontology/District',
            'http://dbpedia.org/ontology/Parish',
        ];
        
        if ($itemSetId) {
            $excludedTypes = array_merge($excludedTypes, [
                "{$this->localBaseUri}$itemSetId/excavation/Location",
                "{$this->localBaseUri}$itemSetId/excavation/GPSCoordinates", 
                "{$this->localBaseUri}$itemSetId/excavation/Archaeologist",
                "{$this->localBaseUri}$itemSetId/excavation/TimeLine",
                "{$this->localBaseUri}$itemSetId/excavation/Instant",
            ]);
        }
        
        $hasExcavationInData = false;
        $hasArrowheadsInData = false;
        
        foreach ($rdfData as $subject => $predicates) {
            if (isset($predicates['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'])) {
                foreach ($predicates['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] as $typeObj) {
                    if ($typeObj['type'] === 'uri') {
                        if ($typeObj['value'] === 'https://purl.org/megalod/ms/excavation/Excavation' ||
                            $typeObj['value'] === 'excav:Excavation' ||
                            ($itemSetId && $typeObj['value'] === "{$this->localBaseUri}$itemSetId/excavation/Excavation")) {
                            $hasExcavationInData = true;
                        }
                        
                        // Check for arrowheads
                        if ($typeObj['value'] === 'https://purl.org/megalod/ms/ah/Arrowhead' ||
                            $typeObj['value'] === 'ah:Arrowhead' ||
                            ($itemSetId && $typeObj['value'] === "{$this->localBaseUri}$itemSetId/ah/Arrowhead")) {
                            $hasArrowheadsInData = true;
                        }
                    }
                }
            }
        }
        
        $isCompleteExcavationUpload = $hasExcavationInData && !$hasArrowheadsInData;
        $isArrowheadOnlyUpload = $hasArrowheadsInData && !$hasExcavationInData;
            
       
       

        if ($itemSetId && !$isCompleteExcavationUpload) {
       

            $arrowheadSubjects = [];
            foreach ($rdfData as $subject => $predicates) {
                if (isset($predicates['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'])) {
                    foreach ($predicates['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] as $typeObj) {
                        if ($typeObj['type'] === 'uri') {
                            if ($typeObj['value'] === 'https://purl.org/megalod/ms/ah/Arrowhead' ||
                                $typeObj['value'] === 'ah:Arrowhead' ||
                                ($itemSetId && $typeObj['value'] === "{$this->localBaseUri}$itemSetId/ah/Arrowhead")) {
                                
                                $arrowheadSubjects[$subject] = 'arrowhead';
       
                            }
                            else if (($typeObj['value'] === 'https://purl.org/megalod/ms/excavation/Item' ||
                                    $typeObj['value'] === 'excav:Item' ||
                                    ($itemSetId && $typeObj['value'] === "{$this->localBaseUri}$itemSetId/excavation/Item")) &&
                                    $this->isMainArrowheadItem($rdfData, $subject)) {
                                
                                $arrowheadSubjects[$subject] = 'item';
       
                            }
                        }
                    }
                }
            }
            
            foreach ($rdfData as $subject => $predicates) {
                if (isset($predicates['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'])) {
                    foreach ($predicates['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] as $typeObj) {
                        if ($typeObj['type'] === 'uri' && 
                            ($typeObj['value'] === 'https://purl.org/megalod/ms/excavation/EncounterEvent' ||
                             $typeObj['value'] === 'excav:EncounterEvent')) {
                            
                            if ($this->isNewEncounterEvent($rdfData, $subject)) {
                                $arrowheadSubjects[$subject] = 'encounter';
       
                            }
                        }
                    }
                }
            }
            
       
            return $arrowheadSubjects;
        }

        
        foreach ($rdfData as $subject => $predicates) {
            if (isset($subjects[$subject]) && $subjects[$subject] === 'excluded') {
                continue;
            }

       
            
            if (isset($predicates['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'])) {
                foreach ($predicates['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] as $typeObj) {
       
                    
                    if ($typeObj['type'] === 'uri' && 
                        $typeObj['value'] !== 'https://purl.org/megalod/ms/excavation/EncounterEvent' &&
                        $typeObj['value'] !== 'https://purl.org/megalod/ms/ah/Morphology' &&
                        $typeObj['value'] !== 'https://purl.org/megalod/ms/ah/Chipping' &&
                        $typeObj['value'] !== 'https://purl.org/megalod/ms/excavation/TypometryValue' &&
                        $typeObj['value'] !== 'https://purl.org/megalod/ms/excavation/Weight' &&
                        $typeObj['value'] !== 'https://purl.org/megalod/ms/excavation/Coordinates' &&
                        !in_array($typeObj['value'], $excludedTypes) &&     
                        isset($mainSubjectTypes[$typeObj['value']])) {
                        
                        $subjects[$subject] = $mainSubjectTypes[$typeObj['value']];
       
                        break; 
                    }
                    
                    if (in_array($typeObj['value'], $excludedTypes)) {
                        $subjects[$subject] = 'excluded';
       
                        break;
                    }
                }
            }
        }
        
        $subjects = array_filter($subjects, function($type) {
            return $type !== 'excluded';
        });
        
       
        
        return $subjects;
    }


    /**
     * Retrieves location data from the excavation item set.
     * This method queries the GraphDB for location information associated with the excavation.
     * @param int $itemSetId The ID of the item set
     * @return array|null An associative array with location details or null if not found
     */

    private function isMainArrowheadItem($rdfData, $subject) {
        if (!isset($rdfData[$subject])) {
            return false;
        }
        
        $predicates = $rdfData[$subject];
        
        $arrowheadProperties = [
            'https://purl.org/megalod/ms/ah/shape',
            'ah:shape',
            'https://purl.org/megalod/ms/ah/variant', 
            'ah:variant',
            'https://purl.org/megalod/ms/ah/hasMorphology',
            'ah:hasMorphology'
        ];
        
        foreach ($arrowheadProperties as $prop) {
            if (isset($predicates[$prop])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Checks if the subject is a new encounter event.
     * This method verifies if the subject has properties indicating a real encounter event.
     * @param mixed $rdfData The RDF data array
     * @param mixed $subject The subject URI to check
     * @return bool True if it's a new encounter event, false otherwise
     */

    private function isNewEncounterEvent($rdfData, $subject) {
        if (!isset($rdfData[$subject])) {
            return false;
        }
        
        $predicates = $rdfData[$subject];
        
       
        $encounterProperties = [
            'https://cidoc-crm.org/extensions/crmsci/O19_encountered_object',
            'crmsci:O19_encountered_object'
        ];
        
        foreach ($encounterProperties as $prop) {
            if (isset($predicates[$prop])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Identifies the main subjects in the RDF data.
     * This method checks for specific patterns in the RDF data to determine the main subjects.
     * @param mixed $rdfData The RDF data array
     * @param int|null $itemSetId The ID of the item set, if available
     * @return array An associative array of main subjects and their types
     */

    private function processArrowheadData($rdfData, $subject, &$itemData) {

        // Current item set context
        $currentItemSetId = $this->getCurrentItemSetContext();
        
        // DIRECT ARROWHEAD PROPERTIES 
        $this->extractDirectArrowheadProperties($rdfData, $subject, $itemData, $currentItemSetId);
        
        // 2. MEASUREMENTS 
        $this->extractAllMeasurements($rdfData, $subject, $itemData, $currentItemSetId);
        
        // 3. MORPHOLOGY DATA 
        $this->extractCompleteMorphologyData($rdfData, $subject, $itemData, $currentItemSetId);
        
        // 4. CHIPPING DATA   
        $this->extractCompleteChippingData($rdfData, $subject, $itemData, $currentItemSetId);
        
        // 5. COORDINATES
        $this->extractCoordinateDataEnhanced($rdfData, $subject, $itemData, $currentItemSetId);
        
        // 6. ENCOUNTER EVENT
        $this->extractEncounterEventData($rdfData, $subject, $itemData, $currentItemSetId);
        
        // 7. ARCHAEOLOGICAL CONTEXT 
        $this->extractArchaeologicalContext($rdfData, $subject, $itemData, $currentItemSetId);
        
        // 8. IMAGES/MEDIA
        $this->extractMediaResources($rdfData, $subject, $itemData);
        
        // 9. GPS COORDINATES
        $this->extractGPSCoordinates($rdfData, $subject, $itemData, $currentItemSetId);
       
    }


    /**
     * Extracts GPS coordinates from the RDF data for a given subject.
     * This method looks for the 'hasGPSCoordinates' property and retrieves latitude and longitude values.
     * @param mixed $rdfData The RDF data array
     * @param mixed $subject The subject URI to extract GPS coordinates from
     * @param array &$itemData The item data array to populate with GPS coordinates
     * @param int|null $currentItemSetId The ID of the current item set, if available
     */

    private function processChippingResource($rdfData, $chippingUri, &$itemData) {
       
        
        // Get current item set for URI normalization
        $currentItemSetId = $this->getCurrentItemSetContext();
        
        $chippingProperties = [
            'chippingMode' => [
                'uris' => [
                    'https://purl.org/megalod/ms/ah/chippingMode', 
                    'ah:chippingMode',
                    "{$this->localBaseUri}$currentItemSetId/ah/chippingMode" 
                ],
                'label' => 'Chipping Mode',
                'propertyId' => 7656,
                'type' => 'uri'
            ],
            'chippingAmplitude' => [
                'uris' => [
                    'https://purl.org/megalod/ms/ah/chippingAmplitude', 
                    'ah:chippingAmplitude',
                    "{$this->localBaseUri}$currentItemSetId/ah/chippingAmplitude" 
                ],
                'label' => 'Chipping Amplitude (Marginal/Deep)',
                'propertyId' => 7657,
                'type' => 'boolean'
            ],
            'chippingDirection' => [
                'uris' => [
                    'https://purl.org/megalod/ms/ah/chippingDirection', 
                    'ah:chippingDirection',
                    "{$this->localBaseUri}$currentItemSetId/ah/chippingDirection" 
                ],
                'label' => 'Chipping Direction',
                'propertyId' => 7658,
                'type' => 'uri'
            ],
            'chippingOrientation' => [
                'uris' => [
                    'https://purl.org/megalod/ms/ah/chippingOrientation', 
                    'ah:chippingOrientation',
                    "{$this->localBaseUri}$currentItemSetId/ah/chippingOrientation" 
                ],
                'label' => 'Chipping Orientation (Lateral/Transverse)',
                'propertyId' => 7659,
                'type' => 'boolean'
            ],
            'chippingDelineation' => [
                'uris' => [
                    'https://purl.org/megalod/ms/ah/chippingDelineation', 
                    'ah:chippingDelineation',
                    "{$this->localBaseUri}$currentItemSetId/ah/chippingDelineation" 
                ],
                'label' => 'Chipping Delineation',
                'propertyId' => 7660,
                'type' => 'uri'
            ],
            'chippingLocationSide' => [
                'uris' => [
                    'https://purl.org/megalod/ms/ah/chippingLocationSide', 
                    'ah:chippingLocationSide',
                    "{$this->localBaseUri}$currentItemSetId/ah/chippingLocationSide" 
                ],
                'label' => 'Chipping Location Side',
                'propertyId' => 7662,
                'type' => 'uri_multiple'
            ],
            'chippingLocationTransversal' => [
                'uris' => [
                    'https://purl.org/megalod/ms/ah/chippingLocationTransversal', 
                    'ah:chippingLocationTransversal',
                    "{$this->localBaseUri}$currentItemSetId/ah/chippingLocationTransversal" 
                ],
                'label' => 'Chipping Location Transversal',
                'propertyId' => 7663,
                'type' => 'uri_multiple'
            ],
            'chippingShape' => [
                'uris' => [
                    'https://purl.org/megalod/ms/ah/chippingShape', 
                    'ah:chippingShape',
                    "{$this->localBaseUri}$currentItemSetId/ah/chippingShape" 
                ],
                'label' => 'Chipping Shape',
                'propertyId' => 7661,
                'type' => 'uri'
            ]
        ];
        
        foreach ($chippingProperties as $propName => $config) {
            foreach ($config['uris'] as $uri) {
                if (isset($rdfData[$chippingUri][$uri])) {
                    if (!isset($itemData[$config['label']])) {
                        $itemData[$config['label']] = [];
                    }
                    
                    foreach ($rdfData[$chippingUri][$uri] as $valueObj) {
                        $value = $this->extractPropertyValue($valueObj, $config['type']);
                        if ($value) {
                            $itemData[$config['label']][] = [
                                'type' => 'literal',
                                'property_id' => $config['propertyId'],
                                '@value' => $value
                            ];
                            
       
                        }
                    }
                    break;
                }
            }
        }
    }

    /**
     * Extracts the value from a property value object.
     * This method handles both literal and URI types, converting boolean values to specific strings if needed.
     * @param array $valueObj The property value object
     * @param string $type The type of the property (default is 'auto')
     * @return mixed The extracted value or null if not applicable
     */

    private function processContextData($rdfData, $subject, &$itemData) {
       
       
        
        $currentItemSetId = $this->getCurrentItemSetContext();
        
        $propertyMap = [
            'http://purl.org/dc/terms/identifier' => ['Context ID', 10],
            'http://purl.org/dc/terms/description' => ['Context Description', 4],
        ];
        
        foreach ($propertyMap as $predicate => $mapping) {
            if (isset($rdfData[$subject][$predicate])) {
                $term = $mapping[0];
                $propertyId = $mapping[1];
                
                if (!isset($itemData[$term])) {
                    $itemData[$term] = [];
                }
                
                foreach ($rdfData[$subject][$predicate] as $object) {
                    if ($object['type'] === 'literal') {
                        $itemData[$term][] = [
                            'type' => 'literal',
                            'property_id' => $propertyId,
                            '@value' => $object['value']
                        ];
                        
       
                    }
                }
            }
        }
        
        $svuPropertyUris = [
            'https://purl.org/megalod/ms/excavation/hasSVU'
        ];
        
        if ($currentItemSetId) {
            $svuPropertyUris[] = "{$this->localBaseUri}$currentItemSetId/excavation/hasSVU";
        }
        
        $linkedSVUs = [];
        $svuDetails = [];
        
        foreach ($svuPropertyUris as $svuPropertyUri) {
            if (isset($rdfData[$subject][$svuPropertyUri])) {
       
                
                foreach ($rdfData[$subject][$svuPropertyUri] as $svuObj) {
                    if ($svuObj['type'] === 'uri') {
                        $svuUri = $svuObj['value'];
                        $svuId = $this->extractResourceIdentifier($rdfData, $svuUri);
                        
                        if ($svuId) {
                            $linkedSVUs[] = $svuId;
                            
                            if (isset($rdfData[$svuUri])) {
                                $svuDescription = null;
                                $svuTimeline = null;
                                
                                // Get SVU description
                                if (isset($rdfData[$svuUri]['http://purl.org/dc/terms/description'])) {
                                    foreach ($rdfData[$svuUri]['http://purl.org/dc/terms/description'] as $descObj) {
                                        if ($descObj['type'] === 'literal') {
                                            $svuDescription = $descObj['value'];
                                            break;
                                        }
                                    }
                                }
                                
                                // Get timeline information
                                $timelinePropertyUris = [
                                    'https://purl.org/megalod/ms/excavation/hasTimeline'
                                ];
                                if ($currentItemSetId) {
                                    $timelinePropertyUris[] = "{$this->localBaseUri}$currentItemSetId/excavation/hasTimeline";
                                }
                                
                                foreach ($timelinePropertyUris as $timelinePropertyUri) {
                                    if (isset($rdfData[$svuUri][$timelinePropertyUri])) {
                                        foreach ($rdfData[$svuUri][$timelinePropertyUri] as $timelineObj) {
                                            if ($timelineObj['type'] === 'uri' && isset($rdfData[$timelineObj['value']])) {
                                                $timelineRange = $this->extractTimelineRange($rdfData, $timelineObj['value']);
                                                if ($timelineRange) {
                                                    $svuTimeline = $timelineRange;
                                                    break 2;
                                                }
                                            }
                                        }
                                    }
                                }
                                
                                $svuDetail = $svuId;
                                if ($svuDescription) {
                                    $svuDetail .= ": $svuDescription";
                                }
                                if ($svuTimeline) {
                                    $svuDetail .= " ($svuTimeline)";
                                }
                                
                                $svuDetails[] = $svuDetail;
                                
       
                            } else {
                                $svuDetails[] = $svuId;
       
                            }
                        }
                    }
                }
                break; 
            }
        }
        
        if (!empty($linkedSVUs)) {
            if (!isset($itemData['Linked Stratigraphic Units'])) {
                $itemData['Linked Stratigraphic Units'] = [];
            }
            
            $itemData['Linked Stratigraphic Units'][] = [
                'type' => 'literal',
                'property_id' => 7667,
                '@value' => implode(', ', $linkedSVUs)
            ];
            
       
        }
        
       
    }

    /**
     * this method extracts the timeline range from RDF data.
     * It retrieves the beginning and end years, along with BC/AD information,
     * and formats it into a human-readable string.
     * @param mixed $rdfData
     * @param mixed $timelineUri
     * @return string|null
     */

    private function processCoordinateResource($rdfData, $coordinateUri, &$itemData) {
       
        
        $coordinates = [
            'X' => null,
            'Y' => null,
            'Z' => null
        ];
        
        if (isset($rdfData[$coordinateUri]['http://schema.org/value'])) {
            $values = $rdfData[$coordinateUri]['http://schema.org/value'];
       
            
            foreach ($values as $index => $valueObj) {
                if ($valueObj['type'] === 'literal') {
                    $key = isset(['X', 'Y', 'Z'][$index]) ? ['X', 'Y', 'Z'][$index] : "Value$index";
                    $coordinates[$key] = $valueObj['value'];
       
                }
            }
        }

        $coordinateProps = [
            'http://www.w3.org/2003/01/geo/wgs84_pos#long' => 'X',
            'http://www.w3.org/2003/01/geo/wgs84_pos#lat' => 'Y',
            'http://schema.org/depth' => 'Z',
            'geo:long' => 'X',
            'geo:lat' => 'Y'
        ];
        
        foreach ($coordinateProps as $propUri => $coord) {
            if (isset($rdfData[$coordinateUri][$propUri])) {
                foreach ($rdfData[$coordinateUri][$propUri] as $valueObj) {
                    if ($valueObj['type'] === 'uri' && isset($rdfData[$valueObj['value']])) {
                        $typometryUri = $valueObj['value'];
       
                        
                        $value = $this->extractMeasurementValue($rdfData, $typometryUri);
                        $unit = $this->extractMeasurementUnit($rdfData, $typometryUri);
                        
                        if ($value !== null) {
                            $coordinates[$coord] = $value . ($unit ? " $unit" : "");
       
                        }
                    } else if ($valueObj['type'] === 'literal') {
                        $coordinates[$coord] = $valueObj['value'];
       
                    }
                }
            }
        }
        
        $coordStrings = [];
        foreach ($coordinates as $axis => $value) {
            if ($value !== null) {
                $coordStrings[] = "$axis: $value";
            }
        }
        
        if (!empty($coordStrings)) {
            if (!isset($itemData['Coordinates'])) {
                $itemData['Coordinates'] = [];
            }
            
            $coordDisplay = implode(', ', $coordStrings);
            $itemData['Coordinates'][] = [
                'type' => 'literal',
                'property_id' => 7674,
                '@value' => $coordDisplay
            ];
            
       
        } else {
       
        }
    }

    /**
     * Processes encounter event data for an arrowhead item.
     * This method extracts encounter dates and encountered objects from the RDF data.
     * @param mixed $rdfData The RDF data array
     * @param mixed $encounterUri The URI of the encounter event resource
     * @param array &$itemData The item data array to populate with encounter properties
     * @param int|null $currentItemSetId The ID of the current item set, if available
     */

    private function processEncounterEvent($rdfData, $encounterUri, &$itemData, $currentItemSetId) {
       
        if (isset($rdfData[$encounterUri]['http://purl.org/dc/terms/date'])) {
            foreach ($rdfData[$encounterUri]['http://purl.org/dc/terms/date'] as $dateObj) {
                if ($dateObj['type'] === 'literal') {
                    if (!isset($itemData['Encounter Date'])) {
                        $itemData['Encounter Date'] = [];
                    }
                    
                    $itemData['Encounter Date'][] = [
                        'type' => 'literal',
                        'property_id' => 7675,
                        '@value' => $dateObj['value']
                    ];
                    
       
                }
            }
        }
        
        $encounteredObjects = [];
        $encounteredItemUris = [];

        if (isset($rdfData[$encounterUri]['crmsci:O19_encountered_object'])) {
        $encounteredRefs = [];
        
        foreach ($rdfData[$encounterUri]['crmsci:O19_encountered_object'] as $objRef) {
            if ($objRef['type'] === 'uri') {
                $objId = $this->extractIdentifierFromUri($objRef['value']) ?: basename($objRef['value']);
                $displayValue = $this->extractResourceDisplayName($rdfData, $objRef['value']) ?: $objId;
                
                $encounteredRefs[] = $displayValue;
                
                if (!isset($itemData['Encountered Item'])) {
                    $itemData['Encountered Item'] = [];
                }
                
                $itemData['Encountered Item'][] = [
                    'type' => 'uri',
                    'property_id' => 374, 
                    '@id' => $objRef['value'],
                    'o:label' => $displayValue
                ];
            }
        }
        
        if (!empty($encounteredRefs)) {
            if (!isset($itemData['Encountered Objects'])) {
                $itemData['Encountered Objects'] = [];
            }
            
            $itemData['Encountered Objects'][] = [
                'type' => 'literal', 
                'property_id' => 374,
                '@value' => implode(', ', $encounteredRefs)
            ];
        }
    }
        
        if (isset($rdfData[$encounterUri]['https://cidoc-crm.org/extensions/crmsci/O19_encountered_object']) || 
            isset($rdfData[$encounterUri]['crmsci:O19_encountered_object'])) {
            
            $propertyVariations = [
                'https://cidoc-crm.org/extensions/crmsci/O19_encountered_object',
                'crmsci:O19_encountered_object'
            ];
            
            if ($currentItemSetId) {
                $propertyVariations[] = "{$this->localBaseUri}$currentItemSetId/crmsci/O19_encountered_object";
            }
            
            foreach ($propertyVariations as $propertyUri) {
                if (isset($rdfData[$encounterUri][$propertyUri])) {
                    foreach ($rdfData[$encounterUri][$propertyUri] as $itemObj) {
                        if ($itemObj['type'] === 'uri') {
                            $itemUri = $itemObj['value'];
                            $encounteredItemUris[] = $itemUri;
                            
                            $itemId = null;
                            $identifier = $this->extractIdentifierFromUri($itemUri);
                            
                            if (isset($rdfData[$itemUri]) && 
                                isset($rdfData[$itemUri]['http://purl.org/dc/terms/identifier'])) {
                                foreach ($rdfData[$itemUri]['http://purl.org/dc/terms/identifier'] as $idObj) {
                                    if ($idObj['type'] === 'literal') {
                                        $identifier = $idObj['value'];
                                    }
                                }
                            }
                            
                            $encounteredObjects[] = [
                                'identifier' => $identifier ?: basename($itemUri),
                                'uri' => $itemUri
                            ];
                        }
                    }
                }
            }
        }
        
        if (!empty($encounteredObjects)) {
            if (!isset($itemData['Encountered Objects'])) {
                $itemData['Encountered Objects'] = [];
            }
            
            $identifierList = array_map(function($obj) { 
                return $obj['identifier']; 
            }, $encounteredObjects);
            
            $itemData['Encountered Objects'][] = [
                'type' => 'literal',
                'property_id' => 374,
                '@value' => implode(', ', $identifierList)
            ];
            
       
            
            if (!isset($itemData['Encountered Item'])) {
                $itemData['Encountered Item'] = [];
            }
            
            foreach ($encounteredObjects as $encObj) {
                $identifier = $encObj['identifier'];
                $uri = $encObj['uri'];
                
                $item = $this->resourceLookup->findItemByIdentifier($identifier, $currentItemSetId);
                
                if ($item) {
                    $itemData['Encountered Item'][] = [
                        'type' => 'resource',
                        'property_id' => 374, 
                        'value_resource_id' => $item->id()
                    ];
                    
       
                } else {
                    $itemData['Encountered Item'][] = [
                        'type' => 'uri',
                        'property_id' => 7686,
                        '@id' => $uri,
                        'o:label' => $identifier
                    ];
                    
       
                }
            }
        } else {
       
        }
        
        if (isset($rdfData[$encounterUri]['http://dbpedia.org/ontology/depth'])) {
            foreach ($rdfData[$encounterUri]['http://dbpedia.org/ontology/depth'] as $depthObj) {
                if ($depthObj['type'] === 'literal') {
                    if (!isset($itemData['Discovery Depth'])) {
                        $itemData['Discovery Depth'] = [];
                    }
                    
                    $itemData['Discovery Depth'][] = [
                        'type' => 'literal',
                        'property_id' => 7676,
                        '@value' => $depthObj['value']
                    ];
                    
       
                }
            }
        }
        
        $contextPatterns = [
            'excav:foundInExcavation' => [
                'label' => 'The Encounter Event - an item found in an Excavation',
                'propertyId' => 7673,
                'variantUris' => [
                    'https://purl.org/megalod/ms/excavation/foundInExcavation',
                    'excav:foundInExcavation'
                ]
            ],
            'excav:foundInLocation' => [
                'label' => 'Item found in a Location',
                'propertyId' => 7680,
                'variantUris' => [
                    'https://purl.org/megalod/ms/excavation/foundInLocation',
                    'excav:foundInLocation'
                ]
            ],
            'excav:foundInSquare' => [
                'label' => 'The Item found in a Square',
                'propertyId' => 7683,
                'variantUris' => [
                    'https://purl.org/megalod/ms/excavation/foundInSquare',
                    'excav:foundInSquare'
                ]
            ],
            'excav:foundInContext' => [
                'label' => 'The Encounter Event - an item found in a specific Context',
                'propertyId' => 7672,
                'variantUris' => [
                    'https://purl.org/megalod/ms/excavation/foundInContext',
                    'excav:foundInContext'
                ]
            ],
            'excav:foundInSVU' => [
                'label' => 'The Item found in a SVU',
                'propertyId' => 7671,
                'variantUris' => [
                    'https://purl.org/megalod/ms/excavation/foundInSVU',
                    'excav:foundInSVU'
                ]
            ]
        ];
        
        if ($currentItemSetId) {
            foreach ($contextPatterns as $key => $config) {
                $baseProperty = str_replace('excav:', '', $key);
                $contextPatterns[$key]['variantUris'][] = "{$this->localBaseUri}$currentItemSetId/excavation/$baseProperty";
            }
        }
        
        foreach ($contextPatterns as $key => $config) {
            $found = false;
            
            foreach ($config['variantUris'] as $predicateUri) {
                if (isset($rdfData[$encounterUri][$predicateUri])) {
       
                    
                    foreach ($rdfData[$encounterUri][$predicateUri] as $contextObj) {
                        if ($contextObj['type'] === 'uri') {
                            $contextUri = $contextObj['value'];
                            $displayValue = $this->extractContextDisplayValue($rdfData, $contextUri) ?: $contextUri;
                            
                            if (!isset($itemData[$config['label']])) {
                                $itemData[$config['label']] = [];
                            }
                            
                            $itemData[$config['label']][] = [
                                'type' => 'uri',
                                'property_id' => $config['propertyId'],
                                '@id' => $contextUri,
                                'o:label' => $displayValue
                            ];
                            
       
                            $found = true;
                        }
                    }
                    
                    if ($found) break; 
                }
            }
        }
        
       
    }

    /**
     * This method extracts the display name for a resource from the RDF data.
     * @param mixed $rdfData
     * @param mixed $resourceUri
     */

    private function processExcavationData($rdfData, $subject, &$itemData) {
       
       
        
        $currentItemSetId = $this->getCurrentItemSetContext();


        
        
        // Extract location information
        if (isset($rdfData[$subject]['http://www.ontologydesignpatterns.org/ont/dul/DUL.owl#hasLocation'])) {
            foreach ($rdfData[$subject]['http://www.ontologydesignpatterns.org/ont/dul/DUL.owl#hasLocation'] as $locObj) {
                if ($locObj['type'] === 'uri' && isset($rdfData[$locObj['value']])) {
                    $locationUri = $locObj['value'];
       
                    
                    // Extract location name
                    if (isset($rdfData[$locationUri]['http://dbpedia.org/ontology/informationName'])) {
                        foreach ($rdfData[$locationUri]['http://dbpedia.org/ontology/informationName'] as $nameObj) {
                            if ($nameObj['type'] === 'literal') {
                                $locationName = $nameObj['value'];
                                
                                if (!isset($itemData['Location Name'])) {
                                    $itemData['Location Name'] = [];
                                }
                                
                                $itemData['Location Name'][] = [
                                    'type' => 'literal',
                                    'property_id' => 1811, 
                                    '@value' => $locationName
                                ];
                                
       
                            }
                        }
                    }
                    
                    
                    $lat = null;
                    $long = null;
                    
                    if (isset($rdfData[$locationUri]['http://www.w3.org/2003/01/geo/wgs84_pos#lat'])) {
                        foreach ($rdfData[$locationUri]['http://www.w3.org/2003/01/geo/wgs84_pos#lat'] as $latObj) {
                            if ($latObj['type'] === 'literal') {
                                $lat = $latObj['value'];
       
                            }
                        }
                    }
                    
                    if (isset($rdfData[$locationUri]['http://www.w3.org/2003/01/geo/wgs84_pos#long'])) {
                        foreach ($rdfData[$locationUri]['http://www.w3.org/2003/01/geo/wgs84_pos#long'] as $longObj) {
                            if ($longObj['type'] === 'literal') {
                                $long = $longObj['value'];
       
                            }
                        }
                    }
                    
                    if ($lat !== null) {
                        if (!isset($itemData['GPS Latitude'])) {
                            $itemData['GPS Latitude'] = [];
                        }
                        $itemData['GPS Latitude'][] = [
                            'type' => 'literal',
                            'property_id' => 257, 
                            '@value' => $lat
                        ];
                    }
                    
                    if ($long !== null) {
                        if (!isset($itemData['GPS Longitude'])) {
                            $itemData['GPS Longitude'] = [];
                        }
                        $itemData['GPS Longitude'][] = [
                            'type' => 'literal',
                            'property_id' => 259, 
                            '@value' => $long
                        ];
                    }
                    
                    // Add combined GPS coordinates
                    if ($lat !== null && $long !== null) {
                        if (!isset($itemData['GPS Coordinates'])) {
                            $itemData['GPS Coordinates'] = [];
                        }
                        $itemData['GPS Coordinates'][] = [
                            'type' => 'literal',
                            'property_id' => 7664, 
                            '@value' => "Latitude: $lat, Longitude: $long"
                        ];
                        
       
                    }
                    
                    $locationProperties = [
                        'http://dbpedia.org/ontology/district' => ['district', 1555],  
                        'http://dbpedia.org/ontology/parish' => ['parish', 1681],      
                        'http://dbpedia.org/ontology/Country' => ['Country', 1402]  
                    ];
                    
                    foreach ($locationProperties as $propertyUri => $propertyInfo) {
                        if (isset($rdfData[$locationUri][$propertyUri])) {
                            $propertyLabel = $propertyInfo[0];
                            $propertyId = $propertyInfo[1];
                            
       
                            
                            foreach ($rdfData[$locationUri][$propertyUri] as $propObj) {
                                if ($propObj['type'] === 'uri') {
                                    $parts = explode('/', $propObj['value']);
                                    $value = str_replace('_', ' ', end($parts));
                                    
                                    if (isset($rdfData[$propObj['value']])) {
                                        $referencedEntity = $rdfData[$propObj['value']];
                                        $nameProperties = [
                                            'http://www.w3.org/2000/01/rdf-schema#label',
                                            'http://dbpedia.org/ontology/name',
                                            'http://purl.org/dc/terms/title'
                                        ];
                                        
                                        foreach ($nameProperties as $nameProp) {
                                            if (isset($referencedEntity[$nameProp])) {
                                                foreach ($referencedEntity[$nameProp] as $nameObj) {
                                                    if ($nameObj['type'] === 'literal') {
                                                        $value = $nameObj['value'];
                                                        break 2;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    
                                    if (!isset($itemData[$propertyLabel])) {
                                        $itemData[$propertyLabel] = [];
                                    }
                                    
                                    $itemData[$propertyLabel][] = [
                                        'type' => 'literal',
                                        'property_id' => $propertyId,
                                        '@value' => $value
                                    ];
                                    
       
                                }
                            }
                        } else {
       
                        }
                    }
                }
            }
        }

        $archaeologistPropertyUris = [
            'https://purl.org/megalod/ms/excavation/hasPersonInCharge'
        ];
        



        
    if (isset($rdfData[$locationUri]['https://purl.org/megalod/ms/excavation/hasGPSCoordinates']) ||
        isset($rdfData[$locationUri]["{$this->localBaseUri}$currentItemSetId/excavation/hasGPSCoordinates"])) {
        
    $gpsPropertyUris = [
        'https://purl.org/megalod/ms/excavation/hasGPSCoordinates',
        "{$this->localBaseUri}$currentItemSetId/excavation/hasGPSCoordinates",
        'excav:hasGPSCoordinates' 
    ];
        
        foreach ($gpsPropertyUris as $gpsPropertyUri) {
            if (isset($rdfData[$locationUri][$gpsPropertyUri])) {
                foreach ($rdfData[$locationUri][$gpsPropertyUri] as $gpsObj) {
                    if ($gpsObj['type'] === 'uri' && isset($rdfData[$gpsObj['value']])) {
                        $gpsUri = $gpsObj['value'];
                        
                        if (isset($rdfData[$gpsUri]['http://www.w3.org/2003/01/geo/wgs84_pos#lat'])) {
                            foreach ($rdfData[$gpsUri]['http://www.w3.org/2003/01/geo/wgs84_pos#lat'] as $latObj) {
                                if ($latObj['type'] === 'literal') {
                                    $lat = $latObj['value'];
       
                                    
                                    if (!isset($itemData['GPS Latitude'])) {
                                        $itemData['GPS Latitude'] = [];
                                    }
                                    $itemData['GPS Latitude'][] = [
                                        'type' => 'literal',
                                        'property_id' => 257, 
                                        '@value' => $lat
                                    ];
                                }
                            }
                        }
                        
                        if (isset($rdfData[$gpsUri]['http://www.w3.org/2003/01/geo/wgs84_pos#long'])) {
                            foreach ($rdfData[$gpsUri]['http://www.w3.org/2003/01/geo/wgs84_pos#long'] as $longObj) {
                                if ($longObj['type'] === 'literal') {
                                    $long = $longObj['value'];
       
                                    
                                    if (!isset($itemData['GPS Longitude'])) {
                                        $itemData['GPS Longitude'] = [];
                                    }
                                    $itemData['GPS Longitude'][] = [
                                        'type' => 'literal',
                                        'property_id' => 259, 
                                        '@value' => $long
                                    ];
                                }
                            }
                        }
                        
                        if (isset($lat) && isset($long)) {
                            if (!isset($itemData['GPS Coordinates'])) {
                                $itemData['GPS Coordinates'] = [];
                            }
                            $itemData['GPS Coordinates'][] = [
                                'type' => 'literal',
                                'property_id' => 7664, 
                                '@value' => "Latitude: $lat, Longitude: $long"
                            ];
                            
       
                        }
                    }
                }
                break;
            }
        }
    }
        
        if ($currentItemSetId) {
            $archaeologistPropertyUris[] = "{$this->localBaseUri}$currentItemSetId/excavation/hasPersonInCharge";
        }
        
        foreach ($archaeologistPropertyUris as $archaeologistPropertyUri) {
            if (isset($rdfData[$subject][$archaeologistPropertyUri])) {
       
                
                foreach ($rdfData[$subject][$archaeologistPropertyUri] as $archaeologistObj) {
                    if ($archaeologistObj['type'] === 'uri' && isset($rdfData[$archaeologistObj['value']])) {
                        $archaeologistUri = $archaeologistObj['value'];
       
                        
                        // Extract archaeologist data
                        $archaeologistData = $this->extractArchaeologistData($rdfData, $archaeologistUri);
                        
                        if ($archaeologistData) {
                            // Add archaeologist name
                            if ($archaeologistData['name']) {
                                if (!isset($itemData['Archaeologist Name'])) {
                                    $itemData['Archaeologist Name'] = [];
                                }
                                
                                $itemData['Archaeologist Name'][] = [
                                    'type' => 'literal',
                                    'property_id' => 7665, 
                                    '@value' => $archaeologistData['name']
                                ];
                                
       
                            }
                            
                            // Add ORCID if available
                            if ($archaeologistData['orcid']) {
                                if (!isset($itemData['Archaeologist ORCID'])) {
                                    $itemData['Archaeologist ORCID'] = [];
                                }
                                
                                $itemData['Archaeologist ORCID'][] = [
                                    'type' => 'literal',
                                    'property_id' => 176,
                                    '@value' => $archaeologistData['orcid']
                                ];
                                
       
                            }
                            
                            // Add email if available
                            if ($archaeologistData['email']) {
                                if (!isset($itemData['Archaeologist Email'])) {
                                    $itemData['Archaeologist Email'] = [];
                                }
                                
                                $itemData['Archaeologist Email'][] = [
                                    'type' => 'literal',
                                    'property_id' => 123, 
                                    '@value' => $archaeologistData['email']
                                ];
                                
       
                            }
                            
                            if (!isset($itemData['Person in Charge'])) {
                                $itemData['Person in Charge'] = [];
                            }
                            
                            $personInfo = $archaeologistData['name'] ?: $archaeologistData['orcid'];
                            if ($archaeologistData['name'] && $archaeologistData['orcid']) {
                                $personInfo = $archaeologistData['name'] . ' (ORCID: ' . $archaeologistData['orcid'] . ')';
                            }
                            
                            $itemData['Person in Charge'][] = [
                                'type' => 'literal',
                                'property_id' => 7665,
                                '@value' => $personInfo
                            ];
                        }
                    }
                }
                break;
            }
        }
        
        $contextList = [];
        $contextPropertyUris = [
            'https://purl.org/megalod/ms/excavation/hasContext'
        ];
        
        if ($currentItemSetId) {
            $contextPropertyUris[] = "{$this->localBaseUri}$currentItemSetId/excavation/hasContext";
        }
        
        foreach ($contextPropertyUris as $contextPropertyUri) {
            if (isset($rdfData[$subject][$contextPropertyUri])) {
       
                
                foreach ($rdfData[$subject][$contextPropertyUri] as $contextObj) {
                    if ($contextObj['type'] === 'uri') {
                        $contextId = $this->extractResourceIdentifier($rdfData, $contextObj['value']);
                        if ($contextId) {
                            $contextList[] = $contextId;
                            
                            if (isset($rdfData[$contextObj['value']])) {
                                $contextDesc = $this->extractContextDescription($rdfData, $contextObj['value']);
                                if ($contextDesc) {
                                    $contextList[count($contextList) - 1] = "$contextId: $contextDesc";
                                }
                            }
                        }
                    }
                }
                break;
            }
        }
        
        if (!empty($contextList)) {
            if (!isset($itemData['Excavation Contexts'])) {
                $itemData['Excavation Contexts'] = [];
            }
            
            $itemData['Excavation Contexts'][] = [
                'type' => 'literal',
                'property_id' => 7666,
                '@value' => implode(' | ', $contextList)
            ];
            
       
        }

        // process svu data
        if (isset($rdfData[$subject]['https://purl.org/megalod/ms/excavation/hasSVU'])) {
            foreach ($rdfData[$subject]['https://purl.org/megalod/ms/excavation/hasSVU'] as $svuObj) {
                if ($svuObj['type'] === 'uri' && isset($rdfData[$svuObj['value']])) {
                    $svuUri = $svuObj['value'];
       
                    
                    // Extract SVU data
                    $svuData = $this->extractSvuData($rdfData, $svuUri);
                    
                    if ($svuData) {
                        // Add SVU name
                        if ($svuData['name']) {
                            if (!isset($itemData['SVU Name'])) {
                                $itemData['SVU Name'] = [];
                            }
                            
                            $itemData['SVU Name'][] = [
                                'type' => 'literal',
                                'property_id' => 7667, 
                                '@value' => $svuData['name']
                            ];
                            
       
                        }
                        
                        // Add SVU description
                        if ($svuData['description']) {
                            if (!isset($itemData['SVU Description'])) {
                                $itemData['SVU Description'] = [];
                            }
                            
                            $itemData['SVU Description'][] = [
                                'type' => 'literal',
                                'property_id' => 7669, 
                                '@value' => $svuData['description']
                            ];
                            
       
                        }
                    }
                }
            }
        }
        
        $squareList = [];
        $squarePropertyUris = [
            'https://purl.org/megalod/ms/excavation/hasSquare'
        ];
        
        if ($currentItemSetId) {
            $squarePropertyUris[] = "{$this->localBaseUri}$currentItemSetId/excavation/hasSquare";
        }
        
        foreach ($squarePropertyUris as $squarePropertyUri) {
            if (isset($rdfData[$subject][$squarePropertyUri])) {
       
                
                foreach ($rdfData[$subject][$squarePropertyUri] as $squareObj) {
                    if ($squareObj['type'] === 'uri') {
                        $squareId = $this->extractResourceIdentifier($rdfData, $squareObj['value']);
                        if ($squareId) {
                            $squareList[] = $squareId;
                            
                            if (isset($rdfData[$squareObj['value']])) {
                                $squareCoords = $this->extractSquareCoordinates($rdfData, $squareObj['value']);
                                if ($squareCoords) {
                                    $squareList[count($squareList) - 1] = "$squareId ($squareCoords)";
                                }
                            }
                        }
                    }
                }
                break;
            }
        }
        
        if (!empty($squareList)) {
            if (!isset($itemData['Excavation Squares'])) {
                $itemData['Excavation Squares'] = [];
            }
            
            $itemData['Excavation Squares'][] = [
                'type' => 'literal',
                'property_id' => 7668,
                '@value' => implode(' | ', $squareList)
            ];
            
       
        }
        
       
    }


    /**
     * This method extracts the description of a context from RDF data.
     * @param mixed $rdfData
     * @param mixed $contextUri
     */

    private function processMorphologyResource($rdfData, $morphologyUri, &$itemData) {
       
        
        // Get current item set for URI normalization
        $currentItemSetId = $this->getCurrentItemSetContext();
        
        $morphologyProperties = [
            'point' => [
                'uris' => [
                    'https://purl.org/megalod/ms/ah/point', 
                    'ah:point',
                    "{$this->localBaseUri}$currentItemSetId/ah/point" 
                ],
                'label' => 'Point Definition (Sharp/Fractured)',
                'propertyId' => 7653,
                'type' => 'boolean'
            ],
            'body' => [
                'uris' => [
                    'https://purl.org/megalod/ms/ah/body', 
                    'ah:body',
                    "{$this->localBaseUri}$currentItemSetId/ah/body" 
                ],
                'label' => 'Body Symmetry (Symmetrical/Non-symmetrical)',
                'propertyId' => 7654,
                'type' => 'boolean'
            ],
            'base' => [
                'uris' => [
                    'https://purl.org/megalod/ms/ah/base', 
                    'ah:base',
                    "{$this->localBaseUri}$currentItemSetId/ah/base" 
                ],
                'label' => 'Base Type',
                'propertyId' => 7655,
                'type' => 'uri'
            ]
        ];
        
        foreach ($morphologyProperties as $propName => $config) {
            foreach ($config['uris'] as $uri) {
                if (isset($rdfData[$morphologyUri][$uri])) {
                    if (!isset($itemData[$config['label']])) {
                        $itemData[$config['label']] = [];
                    }
                    
                    foreach ($rdfData[$morphologyUri][$uri] as $valueObj) {
                        $value = $this->extractPropertyValue($valueObj, $config['type']);
                        if ($value) {
                            $itemData[$config['label']][] = [
                                'type' => 'literal',
                                'property_id' => $config['propertyId'],
                                '@value' => $value
                            ];
                            
       
                        }
                    }
                    break; 
                }
            }
        }
    }

    /**
     * Processes chipping data for an arrowhead item.
     * This method extracts properties related to the chipping characteristics of the arrowhead.
     * @param mixed $rdfData The RDF data array
     * @param mixed $chippingUri The URI of the chipping resource
     * @param array &$itemData The item data array to populate with chipping properties
     */

    private function processSVUData($rdfData, $subject, &$itemData) {
       
       
        
        $currentItemSetId = $this->getCurrentItemSetContext();
        
        $propertyMap = [
            'http://purl.org/dc/terms/identifier' => ['SVU ID', 10],
            'http://purl.org/dc/terms/description' => ['Description', 4],
        ];
        
        foreach ($propertyMap as $predicate => $mapping) {
            if (isset($rdfData[$subject][$predicate])) {
                $term = $mapping[0];
                $propertyId = $mapping[1];
                
                if (!isset($itemData[$term])) {
                    $itemData[$term] = [];
                }
                
                foreach ($rdfData[$subject][$predicate] as $object) {
                    if ($object['type'] === 'literal') {
                        $itemData[$term][] = [
                            'type' => 'literal',
                            'property_id' => $propertyId,
                            '@value' => $object['value']
                        ];
                        
       
                    }
                }
            } else {
       
            }
        }
        
        $timelinePropertyUris = [
            'https://purl.org/megalod/ms/excavation/hasTimeline'
        ];
        
        if ($currentItemSetId) {
            $timelinePropertyUris[] = "{$this->localBaseUri}$currentItemSetId/excavation/hasTimeline";
        }
        
        foreach ($timelinePropertyUris as $timelinePropertyUri) {
            if (isset($rdfData[$subject][$timelinePropertyUri])) {
       
                
                foreach ($rdfData[$subject][$timelinePropertyUri] as $timelineObj) {
                    if ($timelineObj['type'] === 'uri' && isset($rdfData[$timelineObj['value']])) {
                        $timelineUri = $timelineObj['value'];
       
                        
                        // Extract beginning and end points
                        $beginningYear = null;
                        $beginningBC = null;
                        $endYear = null;
                        $endBC = null;
                        
                        // Extract beginning
                        if (isset($rdfData[$timelineUri]['http://www.w3.org/2006/time#hasBeginning'])) {
                            foreach ($rdfData[$timelineUri]['http://www.w3.org/2006/time#hasBeginning'] as $beginObj) {
                                if ($beginObj['type'] === 'uri' && isset($rdfData[$beginObj['value']])) {
                                    $beginUri = $beginObj['value'];
       
                                    
                                    // Extract year
                                    if (isset($rdfData[$beginUri]['http://www.w3.org/2006/time#inXSDgYear'])) {
                                        foreach ($rdfData[$beginUri]['http://www.w3.org/2006/time#inXSDgYear'] as $yearObj) {
                                            if ($yearObj['type'] === 'literal') {
                                                $beginningYear = abs((int)$yearObj['value']); 
       
                                            }
                                        }
                                    }
                                    
                                    $bcadPropertyUris = [
                                        'https://purl.org/megalod/ms/excavation/bcad'
                                    ];
                                    if ($currentItemSetId) {
                                        $bcadPropertyUris[] = "{$this->localBaseUri}$currentItemSetId/excavation/bcad";
                                    }
                                    
                                    foreach ($bcadPropertyUris as $bcadPropertyUri) {
                                        if (isset($rdfData[$beginUri][$bcadPropertyUri])) {
                                            foreach ($rdfData[$beginUri][$bcadPropertyUri] as $bcObj) {
                                                if ($bcObj['type'] === 'uri') {
                                                    $parts = explode('/', $bcObj['value']);
                                                    $bcacValue = end($parts);
                                                    $beginningBC = ($bcacValue === 'BC');
       
                                                    break 2;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        if (isset($rdfData[$timelineUri]['http://www.w3.org/2006/time#hasEnd'])) {
                            foreach ($rdfData[$timelineUri]['http://www.w3.org/2006/time#hasEnd'] as $endObj) {
                                if ($endObj['type'] === 'uri' && isset($rdfData[$endObj['value']])) {
                                    $endUri = $endObj['value'];
       
                                    
                                    if (isset($rdfData[$endUri]['http://www.w3.org/2006/time#inXSDgYear'])) {
                                        foreach ($rdfData[$endUri]['http://www.w3.org/2006/time#inXSDgYear'] as $yearObj) {
                                            if ($yearObj['type'] === 'literal') {
                                                $endYear = abs((int)$yearObj['value']); 
       
                                            }
                                        }
                                    }
                                    
                                    // Extract BC/AD
                                    $bcadPropertyUris = [
                                        'https://purl.org/megalod/ms/excavation/bcad'
                                    ];
                                    if ($currentItemSetId) {
                                        $bcadPropertyUris[] = "{$this->localBaseUri}$currentItemSetId/excavation/bcad";
                                    }
                                    
                                    foreach ($bcadPropertyUris as $bcadPropertyUri) {
                                        if (isset($rdfData[$endUri][$bcadPropertyUri])) {
                                            foreach ($rdfData[$endUri][$bcadPropertyUri] as $bcObj) {
                                                if ($bcObj['type'] === 'uri') {
                                                    $parts = explode('/', $bcObj['value']);
                                                    $bcacValue = end($parts);
                                                    $endBC = ($bcacValue === 'BC');
       
                                                    break 2;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        
                        
                        // combined timeline range
                        if ($beginningYear && $endYear) {
                            if (!isset($itemData['Chronological Period'])) {
                                $itemData['Chronological Period'] = [];
                            }
                            
                            $beginText = $beginningYear . ($beginningBC ? ' BC' : ' AD');
                            $endText = $endYear . ($endBC ? ' BC' : ' AD');
                            $timelineRange = "$beginText - $endText";
                            
                            $itemData['Chronological Period'][] = [
                                'type' => 'literal',
                                'property_id' => 7669, 
                                '@value' => $timelineRange
                            ];
                            
       
                        }
                    }
                }
                break; 
            } else {
       
            }
        }

        if (isset($rdfData[$subject]['https://purl.org/megalod/ms/excavation/hasTimeline'])) {
        foreach ($rdfData[$subject]['https://purl.org/megalod/ms/excavation/hasTimeline'] as $timelineObj) {
            if ($timelineObj['type'] === 'uri' && isset($rdfData[$timelineObj['value']])) {
                $timelineUri = $timelineObj['value'];
                
                $timelineRange = $this->extractTimelineRange($rdfData, $timelineUri);
                if ($timelineRange) {
                    if (!isset($itemData['Chronological Period'])) {
                        $itemData['Chronological Period'] = [];
                    }
                    
                    $itemData['Chronological Period'][] = [
                        'type' => 'literal',
                        'property_id' => 7669,
                        '@value' => $timelineRange
                    ];
                }
            }
        }
    }
        
       
    }



    /**
     * Processes context data from RDF and populates item data.
     * This method extracts context ID, description, and linked SVUs from RDF data.
     * @param array $rdfData The RDF data containing context information
     * @param string $subject The subject URI to process
     * @param array &$itemData The item data to populate with extracted information
     */

    private function processSquareData($rdfData, $subject, &$itemData) {
        $propertyMap = [
            'http://purl.org/dc/terms/identifier' => ['Square ID', 10],
            'http://www.w3.org/2003/01/geo/wgs84_pos#long' => ['North-South Quota', 259],
            'http://www.w3.org/2003/01/geo/wgs84_pos#lat' => ['East-West Quota', 257]
        ];
        
        foreach ($propertyMap as $predicate => $mapping) {
            if (isset($rdfData[$subject][$predicate])) {
                $term = $mapping[0];
                $propertyId = $mapping[1];
                
                if (!isset($itemData[$term])) {
                    $itemData[$term] = [];
                }
                
                foreach ($rdfData[$subject][$predicate] as $object) {
                    if ($object['type'] === 'literal') {
                        $itemData[$term][] = [
                            'type' => 'literal',
                            'property_id' => $propertyId,
                            '@value' => $object['value']
                        ];
                    }
                }
            }
        }
        
        if (isset($rdfData[$subject]['https://purl.org/megalod/ms/excavation/foundInExcavation'])) {
            foreach ($rdfData[$subject]['https://purl.org/megalod/ms/excavation/foundInExcavation'] as $excObj) {
                if ($excObj['type'] === 'uri') {
                    if (!isset($itemData['The Encounter Event - an item found in an Excavation'])) {
                        $itemData['The Encounter Event - an item found in an Excavation'] = [];
                    }
                    
                    $itemData['The Encounter Event - an item found in an Excavation'][] = [
                        'type' => 'uri',
                        'property_id' => 7673, 
                        '@id' => $excObj['value'],
                        '@value' => $excObj['value'] 
                    ];
                }
            }
        }
        
        if (isset($rdfData[$subject]['https://purl.org/megalod/ms/excavation/foundInContext'])) {
            foreach ($rdfData[$subject]['https://purl.org/megalod/ms/excavation/foundInContext'] as $ctxObj) {
                if ($ctxObj['type'] === 'uri') {
                    if (!isset($itemData['The Encounter Event - an item found in a specific Context'])) {
                        $itemData['The Encounter Event - an item found in a specific Context'] = [];
                    }
                    
                    $itemData['The Encounter Event - an item found in a specific Context'][] = [
                        'type' => 'uri',
                        'property_id' => 7672, 
                        '@id' => $ctxObj['value'],
                        '@value' => $ctxObj['value'] 
                    ];
                }
            }
        }
        
        if (isset($rdfData[$subject]['https://purl.org/megalod/ms/excavation/foundInSVU'])) {
            foreach ($rdfData[$subject]['https://purl.org/megalod/ms/excavation/foundInSVU'] as $svuObj) {
                if ($svuObj['type'] === 'uri') {
                    if (!isset($itemData['Stratigraphic Unit'])) {
                        $itemData['Stratigraphic Unit'] = [];
                    }
                    
                    $itemData['Stratigraphic Unit'][] = [
                        'type' => 'uri',
                        'property_id' => 7671, 
                        '@id' => $svuObj['value'],
                        '@value' => $svuObj['value'] 
                    ];
                }
            }
        }
        
        if (isset($rdfData[$subject]['https://purl.org/megalod/ms/excavation/foundInSquare'])) {
            foreach ($rdfData[$subject]['https://purl.org/megalod/ms/excavation/foundInSquare'] as $squareObj) {
                if ($squareObj['type'] === 'uri') {
                    if (!isset($itemData['Excavation Square'])) {
                        $itemData['Excavation Square'] = [];
                    }
                    
                    $itemData['Excavation Square'][] = [
                        'type' => 'uri',
                        'property_id' => 7668, 
                        '@id' => $squareObj['value'],
                        '@value' => $squareObj['value'] 
                    ];
                }
            }
        }
    }


    /**
     * Extracts archaeologist data from RDF data.
     * This method retrieves the name, ORCID, and email of the archaeologist from the RDF data.
     * @param array $rdfData The RDF data containing archaeologist information
     * @param string $archaeologistUri The URI of the archaeologist to extract data for
     * @return array|null An associative array with archaeologist data or null if not found
     */

    /**
     * @return list<array<string, mixed>>
     */
    public function transformTtlToOmekaItemPayloads(string $ttlData, ?int $itemSetId, ?string $resolvedExcavationId): array
    {
        $graph = new \EasyRdf\Graph();
        $graph->parse($ttlData, 'turtle');

        $omekaData = [];
        $rdfData = $graph->toRdfPhp();

        $this->contextItemSetId = $itemSetId;
        $mainSubjects = $this->identifyMainSubjects($rdfData, $itemSetId);

        $excavationId = $resolvedExcavationId !== null && $resolvedExcavationId !== '' ? $resolvedExcavationId : '0';

        // Process each  subject as a separate item
        foreach ($mainSubjects as $subject => $subjectType) {
            $itemData = [
                'o:resource_class' => ['o:id' => 1], 
                'o:item_set' => [],
            ];
            
            if ($itemSetId) {
                $itemData['o:item_set'][] = ['o:id' => $itemSetId];
            }
            
            $identifier = $this->extractIdentifier($rdfData, $subject);
            if ($identifier) {
                $itemData['dcterms:identifier'] = [
                    [
                        'type' => 'literal',
                        'property_id' => 10,
                        '@value' => $identifier
                    ]
                ];
            }
            
            $itemType = $this->determineItemType($subjectType);
            $title = $itemType;
            if ($identifier) {
                $title .= " " . $identifier;
                if ($excavationId != "0") {
                    $title .= " (Excavation $excavationId)";
                }
            }
            
            $itemData['dcterms:title'] = [
                [
                    'type' => 'literal',
                    'property_id' => 1,
                    '@value' => $title
                ]
            ];
            
            $this->extractCommonProperties($rdfData, $subject, $itemData);
       
            switch ($subjectType) {
                case 'arrowhead':
                case 'item':
                    $this->processArrowheadData($rdfData, $subject, $itemData);
                    break;
                case 'excavation':
       
                    $this->processExcavationData($rdfData, $subject, $itemData);
                    break;
                case 'context':
                    $this->processContextData($rdfData, $subject, $itemData);
                    break;
                case 'svu':
                    $this->processSVUData($rdfData, $subject, $itemData);
                    break;
                case 'square':
                    $this->processSquareData($rdfData, $subject, $itemData);
                    break;
            }
            
            $omekaData[] = $itemData;
        }

       
        
        return $omekaData;
    }
}
