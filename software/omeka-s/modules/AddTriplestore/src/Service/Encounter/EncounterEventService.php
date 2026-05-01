<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Encounter;

use AddTriplestore\Service\ExcavationItemSetContextService;
use AddTriplestore\Service\GraphDbHttpService;
use AddTriplestore\Service\Ttl\TtlUriHelper;
use Omeka\Api\Manager as ApiManager;

/**
 * Arrowhead encounter extraction, validation, Omeka EncounterEvent items, and TTL enrichment.
 */
class EncounterEventService
{
    /** @var ApiManager */
    private $api;

    /** @var GraphDbHttpService */
    private $graphDbHttpService;

    /** @var ExcavationItemSetContextService */
    private $excavationItemSetContextService;

    /** @var TtlUriHelper */
    private $ttlUriHelper;

    /** @var string MegaLOD public base URI trailing slash semantics match controller historical state */
    private $publicBaseUri;

    /** @var string Local base URI used in generated resource IRIs */
    private $localBaseUriStr;

    /** @var array<string, array> */
    private $signatureContextCache = [];

    public function __construct(
        ApiManager $api,
        GraphDbHttpService $graphDbHttpService,
        ExcavationItemSetContextService $excavationItemSetContextService,
        TtlUriHelper $ttlUriHelper,
        string $publicBaseUri,
        string $localBaseUriStr
    ) {
        $this->api = $api;
        $this->graphDbHttpService = $graphDbHttpService;
        $this->excavationItemSetContextService = $excavationItemSetContextService;
        $this->ttlUriHelper = $ttlUriHelper;
        $this->publicBaseUri = $publicBaseUri;
        $this->localBaseUriStr = $localBaseUriStr;
    }

    /**
     * If TTL looks like an arrowhead item and itemSetId given, validates context links, ensures EncounterEvent exists, patches TTL.
     *
     * @return array{ok: bool, ttl: string, error: ?string, details?: array<string, mixed>}
     */
    public function applyEncounterForArrowheadUpload(string $ttlData, ?int $itemSetId): array
    {
        $isArrowhead = strpos($ttlData, 'ah:Arrowhead') !== false || strpos($ttlData, 'excav:Item') !== false;

        if (!$isArrowhead || !$itemSetId) {
            return ['ok' => true, 'ttl' => $ttlData, 'error' => null];
        }

        $arrowheadContext = $this->extractArrowheadContextFromTtl($ttlData);
        $validationResult = $this->validateContextRelationships($arrowheadContext, $itemSetId);

        if (!$validationResult['valid']) {
            $details = "\n\nValidation Details:\n" . json_encode($validationResult['details'], JSON_PRETTY_PRINT);
            $msg = implode('; ', $validationResult['errors'] ?? []);

            return [
                'ok' => false,
                'ttl' => $ttlData,
                'error' => 'Validation Error: ' . $msg . $details,
                'details' => $validationResult['details'] ?? [],
            ];
        }

        $encounterEvent = $this->findOrCreateEncounterEvent($arrowheadContext, $itemSetId);

        return [
            'ok' => true,
            'ttl' => $this->addEncounterEventToTtl($ttlData, $encounterEvent, $itemSetId),
            'error' => null,
        ];
    }

    private function extractArrowheadContextFromTtl($ttlData) {
        $context = [
            'excavation' => null,
            'location' => null,
            'square' => null,
            'context' => null,
            'svu' => null,
            'date' => null,
            'item_identifier' => null
        ];




        // Extract item identifier
        if (preg_match('/dct:identifier\s+"([^"]+)"/i', $ttlData, $matches)) {
            $context['item_identifier'] = $matches[1];

        }

        // Extract date
        if (preg_match('/dct:date\s+"([^"]+)"/i', $ttlData, $matches)) {
            $context['date'] = $matches[1];

        }

        // IMPROVED: Extract context reference with better pattern matching
        if (preg_match('/excav:foundInContext\s+<([^>]+)>/i', $ttlData, $matches)) {
            $contextUri = $matches[1];


            // Extract the context identifier from declaration
            if (preg_match('/<' . preg_quote($contextUri, '/') . '>\s+a\s+excav:Context\s*;\s*dct:identifier\s+"([^"]+)"(?:\^\^xsd:literal)?/i', $ttlData, $idMatches)) {
                $context['context'] = $idMatches[1];

            } else {
                // extract from URI structure 
                if (preg_match('/\/context\/([^\/\s>]+)/', $contextUri, $contextMatches)) {
                    $context['context'] = $contextMatches[1];

                }
            }
        }

        if (!$context['context']) {
            if (preg_match('/<[^>]*\/context\/([^>\/\s]+)>\s+a\s+excav:Context/i', $ttlData, $matches)) {
                $context['context'] = $matches[1];

            }
        }

        if (preg_match('/excav:foundInSVU\s+<([^>]+)>/i', $ttlData, $matches)) {
            $svuUri = $matches[1];


            if (preg_match('/<' . preg_quote($svuUri, '/') . '>\s+a\s+excav:StratigraphicVolumeUnit\s*;\s*dct:identifier\s+"([^"]+)"(?:\^\^xsd:literal)?/i', $ttlData, $idMatches)) {
                $context['svu'] = $idMatches[1];

            } else {
                if (preg_match('/\/svu\/([^\/\s>]+)/', $svuUri, $svuMatches)) {
                    $context['svu'] = $svuMatches[1];

                }
            }
        }

        if (!$context['svu']) {
            if (preg_match('/<[^>]*\/svu\/([^>\/\s]+)>\s+a\s+excav:StratigraphicVolumeUnit/i', $ttlData, $matches)) {
                $context['svu'] = $matches[1];

            }
        }



        if (preg_match('/excav:foundInLocation\s+<([^>]+)>/i', $ttlData, $matches)) {
            $locationUri = $matches[1];


            if (preg_match('/<' . preg_quote($locationUri, '/') . '>\s+a\s+excav:Location\s*;\s*dbo:informationName\s+"([^"]+)"/i', $ttlData, $nameMatches)) {
                $context['location'] = $this->ttlUriHelper->createUrlSlug($nameMatches[1]);

            } else {
                if (preg_match('/\/location\/([^\/]+)$/', $locationUri, $locationMatches)) {
                    $context['location'] = $locationMatches[1];

                }
            }
        }

        if (preg_match('/excav:foundInExcavation\s+<([^>]+)>/i', $ttlData, $matches)) {
            $excavationUri = $matches[1];


            if (preg_match('/<' . preg_quote($excavationUri, '/') . '>\s+a\s+excav:Excavation\s*;\s*dct:identifier\s+"([^"]+)"/i', $ttlData, $idMatches)) {
                $context['excavation'] = $idMatches[1];

            } else {
                if (preg_match('/\/excavation\/([^\/]+)(?:\/|$)/', $excavationUri, $excavationMatches)) {
                    $context['excavation'] = $excavationMatches[1];

                }
            }
        }

        if (!$context['excavation'] && preg_match('/encounter\/encounter-(\d+)/', $ttlData, $matches)) {
            if (preg_match('/excav:EncounterEvent\s*;\s*.*?excav:foundInExcavation\s+<([^>]+)>/s', $ttlData, $encMatches)) {
                if (preg_match('/\/excavation\/([^\/]+)(?:\/|$)/', $encMatches[1], $excavationMatches)) {
                    $context['excavation'] = $excavationMatches[1];

                }
            }
        }

        if (preg_match('/excav:foundInSquare\s+<([^>]+)>/i', $ttlData, $matches)) {
            $squareUri = $matches[1];
            if (preg_match('/\/square\/([^\/\s>]+)/', $squareUri, $squareMatches)) {
                $context['square'] = $squareMatches[1];

            }
        } 

        $hasValidContext = $context['excavation'] || $context['location'] || $context['context'] || $context['svu'] || $context['square'];




        return $context;
    }

    /**
     * Validates the context relationships for an arrowhead item.
     * This method checks if the context and SVU exist in the excavation and if their relationship is valid.
     * @param array $arrowheadContext The context data extracted from the arrowhead
     * @param int $itemSetId The ID of the item set to validate against
     * @return array An array containing validation results, errors, and details
     */
    private function validateContextRelationships($arrowheadContext, $itemSetId) {


        $excavationRelationships = $this->getExcavationRelationshipsFromGraphDB($itemSetId);

        $errors = [];
        $details = [];



        if ($arrowheadContext['svu']) {

            if (strpos($arrowheadContext['svu'], '/') !== false) {
                $parts = explode('/', rtrim($arrowheadContext['svu'], '/'));
                $arrowheadContext['svu'] = end($parts);
            }

            if (!in_array($arrowheadContext['svu'], $excavationRelationships['svus'])) {
                $errors[] = "SVU '{$arrowheadContext['svu']}' does not exist in this excavation";
                $details['available_svus'] = $excavationRelationships['svus'];
            }
        }

        if ($arrowheadContext['context']) {
            if (strpos($arrowheadContext['context'], '/') !== false) {
                $parts = explode('/', rtrim($arrowheadContext['context'], '/'));
                $arrowheadContext['context'] = end($parts);
            }

            if (!in_array($arrowheadContext['context'], $excavationRelationships['contexts'])) {
                $errors[] = "Context '{$arrowheadContext['context']}' does not exist in this excavation";
                $details['available_contexts'] = $excavationRelationships['contexts'];
            }
        }

        if ($arrowheadContext['context'] && $arrowheadContext['svu']) {
            $relationshipExists = false;
            foreach ($excavationRelationships['context_svu_links'] as $link) {
                if ($link['context'] === $arrowheadContext['context'] && 
                    $link['svu'] === $arrowheadContext['svu']) {
                    $relationshipExists = true;
                    break;
                }
            }

            if (!$relationshipExists) {
                $errors[] = "Invalid relationship: Context '{$arrowheadContext['context']}' is not linked to SVU '{$arrowheadContext['svu']}' in this excavation";
                $details['valid_relationships'] = $excavationRelationships['context_svu_links'];
            }
        }



        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'details' => $details
        ];
    }

    /**
     * Retrieves excavation relationships from the GraphDB for a given item set ID.
     * This method queries the GraphDB to find contexts and SVUs related to the item set.
     * @param int $itemSetId The ID of the item set to query
     * @return array An array containing contexts, SVUs, and context-SVU links
     */
    private function getExcavationRelationshipsFromGraphDB($itemSetId) {
        $graphUri = $this->publicBaseUri . $itemSetId . "/";
        $query = "
        PREFIX excav: <https://purl.org/megalod/ms/excavation/>
        PREFIX dct: <http://purl.org/dc/terms/>
        PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>

        SELECT DISTINCT ?contextId ?svuId ?hasRelationship
        WHERE {
            GRAPH <$graphUri> {
                {
                    ?context a excav:Context ;
                            dct:identifier ?contextId .
                    OPTIONAL {
                        ?context excav:hasSVU ?svu .
                        ?svu dct:identifier ?svuId .
                        BIND(true AS ?hasRelationship)
                    }
                }
                UNION
                {
                    ?svu a excav:StratigraphicVolumeUnit ;
                         dct:identifier ?svuId .
                }
                UNION
                {
                    # Also check public URIs format
                    ?context excav:hasSVU ?publicSvu .
                    BIND(REPLACE(str(?publicSvu), '.*/([^/]+)$', '$1') as ?svuId)
                    BIND(true AS ?hasRelationship)
                }
            }
        }";

        try {
            $results = $this->graphDbHttpService->postSparqlJson($query);

            if (!empty($results) && isset($results['results']['bindings'])) {
                $contexts = [];
                $svus = [];
                $contextSvuLinks = [];

                foreach ($results['results']['bindings'] as $binding) {
                    if (isset($binding['contextId'])) {
                        $contexts[] = $binding['contextId']['value'];
                    }
                    if (isset($binding['svuId'])) {
                        $svus[] = $binding['svuId']['value'];
                    }
                    if (isset($binding['hasRelationship']) && 
                        isset($binding['contextId']) && 
                        isset($binding['svuId'])) {
                        $contextSvuLinks[] = [
                            'context' => $binding['contextId']['value'],
                            'svu' => $binding['svuId']['value']
                        ];
                    }
                }

                return [
                    'contexts' => array_unique($contexts),
                    'svus' => array_unique($svus),
                    'context_svu_links' => $contextSvuLinks
                ];
            }
        } catch (\Exception $e) {

        }

        return [
            'contexts' => [],
            'svus' => [],
            'context_svu_links' => []
        ];
    }

    /**
     * Finds or creates an encounter event based on the arrowhead context.
     * This method checks if an encounter event already exists for the given context and item set.
     * If not, it creates a new encounter event.
     * @param array $arrowheadContext The context data extracted from the arrowhead
     * @param int $itemSetId The ID of the item set to create or find the encounter event in
     * @return array|null The encounter event data or null if not found or created
     */
    private function findOrCreateEncounterEvent($arrowheadContext, $itemSetId) {


        $encounterSignature = $this->generateEncounterSignature($arrowheadContext);

        // Check if encounter event already exists
        $existingEncounter = $this->findExistingEncounterEvent($encounterSignature, $itemSetId);

        if ($existingEncounter) {

            return $existingEncounter;
        }

        // Create new encounter event
        $newEncounter = $this->createNewEncounterEvent($arrowheadContext, $itemSetId, $encounterSignature);


        return $newEncounter;
    }

    /**
     * Generates a unique signature for the encounter based on the context.
     * This method creates a hash signature that uniquely identifies the encounter event.
     * @param array $context The context data for the encounter
     * @return string The generated MD5 signature
     */
    private function generateEncounterSignature($context) {
        $signature = [
            'excavation' => $context['excavation'] ?: 'unknown',
            'context' => $context['context'] ?: 'no-context',
            'svu' => $context['svu'] ?: 'no-svu',
            'date' => $context['date'],
            'square' => $context['square'] ?: 'no-square'
        ];

        return md5(json_encode($signature));
    }

    /**
     * Finds an existing encounter event by signature or title.
     * This method searches for an encounter event in the specified item set that matches the given signature.
     * If no match is found by signature, it tries to find by title.
     * @param string $signature The MD5 signature of the encounter
     * @param int $itemSetId The ID of the item set to search in
     * @return array|null The encounter event data or null if not found
     */
    private function findExistingEncounterEvent($signature, $itemSetId) {
        try {
            $searchParams = [
                'resource_class_id' => 123,
                'item_set_id' => $itemSetId,
                'property' => [
                    [
                        'property' => 10,
                        'type' => 'eq',
                        'text' => $signature
                    ]
                ]
            ];

            $response = $this->api->search('items', $searchParams);
            $encounters = $response->getContent();

            if (!empty($encounters)) {
                $encounter = $encounters[0];

                return [
                    'id' => $encounter->id(),
                    'signature' => $signature,
                    'omeka_id' => $encounter->id()
                ];
            }

            $title = $this->generateEncounterTitle($this->contextFromSignature($signature));

            $titleSearchParams = [
                'resource_class_id' => 123,
                'item_set_id' => $itemSetId,
                'property' => [
                    [
                        'property' => 1, 
                        'type' => 'eq',
                        'text' => $title
                    ]
                ]
            ];

            $response = $this->api->search('items', $titleSearchParams);
            $encounters = $response->getContent();

            if (!empty($encounters)) {
                $encounter = $encounters[0];

                return [
                    'id' => $encounter->id(),
                    'signature' => $signature, 
                    'omeka_id' => $encounter->id()
                ];
            }
        } catch (\Exception $e) {

        }

        return null;
    }
    /**
     * This method attempts to reverse engineer the context from a signature.
     * @param mixed $signature
     * @return array|array{context: string, date: string, excavation: string, square: string, svu: string}
     */
    private function contextFromSignature($signature) {


        // Return from cache if already processed
        if (isset($this->signatureContextCache[$signature])) {
            return $this->signatureContextCache[$signature];
        }


        $context = [
            'excavation' => 'unknown',
            'context' => 'unknown',
            'svu' => 'unknown',
            'date' => date('Y-m-d'),
            'square' => 'unknown'
        ];

        try {
            $searchParams = [
                'property' => [
                    [
                        'property' => 10, 
                        'type' => 'eq',
                        'text' => $signature
                    ]
                ]
            ];

            $response = $this->api->search('items', $searchParams);
            $encounters = $response->getContent();

            if (!empty($encounters)) {
                $encounter = $encounters[0];

                $values = $encounter->values();


                if (isset($values['excav:foundInExcavation'])) {
                    $propertyValues = $values['excav:foundInExcavation'];
                    if (is_array($propertyValues)) {
                        foreach ($propertyValues as $valueRepresentation) {
                            if (method_exists($valueRepresentation, 'uri') && $valueRepresentation->uri()) {
                                $context['excavation'] = $valueRepresentation->uri();
                            } elseif (method_exists($valueRepresentation, 'value')) {
                                $context['excavation'] = $valueRepresentation->value();
                            }
                            break;
                        }
                    }
                }

                if (isset($values['excav:foundInContext'])) {
                    $propertyValues = $values['excav:foundInContext'];
                    if (is_array($propertyValues)) {
                        foreach ($propertyValues as $valueRepresentation) {
                            if (method_exists($valueRepresentation, 'uri') && $valueRepresentation->uri()) {
                                $context['context'] = $valueRepresentation->uri();
                            } elseif (method_exists($valueRepresentation, 'value')) {
                                $context['context'] = $valueRepresentation->value();
                            }
                            break;
                        }
                    }
                }

                if (isset($values['excav:foundInSVU'])) {
                    $propertyValues = $values['excav:foundInSVU'];
                    if (is_array($propertyValues)) {
                        foreach ($propertyValues as $valueRepresentation) {
                            if (method_exists($valueRepresentation, 'uri') && $valueRepresentation->uri()) {
                                $context['svu'] = $valueRepresentation->uri();
                            } elseif (method_exists($valueRepresentation, 'value')) {
                                $context['svu'] = $valueRepresentation->value();
                            }
                            break;
                        }
                    }
                }

                // Get date
                if (isset($values['dcterms:date'])) {

                    $propertyValues = $values['dcterms:date'];


                    if (is_array($propertyValues)) {
                        foreach ($propertyValues as $valueRepresentation) {

                            if (method_exists($valueRepresentation, 'value')) {
                                $context['date'] = $valueRepresentation->value();

                            }
                            break;
                        }
                    } else {

                        if (method_exists($propertyValues, 'values')) {
                            $actualValues = $propertyValues->values();
                            foreach ($actualValues as $valueRepresentation) {
                                if (method_exists($valueRepresentation, 'value')) {
                                    $context['date'] = $valueRepresentation->value();
                                    break;
                                }
                            }
                        }
                    }
                }

                // Get square
                if (isset($values['excav:foundInSquare'])) {
                    $propertyValues = $values['excav:foundInSquare'];
                    if (is_array($propertyValues)) {
                        foreach ($propertyValues as $valueRepresentation) {
                            if (method_exists($valueRepresentation, 'uri') && $valueRepresentation->uri()) {
                                $context['square'] = $valueRepresentation->uri();
                            } elseif (method_exists($valueRepresentation, 'value')) {
                                $context['square'] = $valueRepresentation->value();
                            }
                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {

        }

        $this->signatureContextCache[$signature] = $context;

        return $context;
    }


    /**
     * Creates a new encounter event in the specified item set.
     * This method constructs the encounter data and sends it to the API to create a new item.
     * @param array $context The context data for the encounter
     * @param int $itemSetId The ID of the item set to create the encounter in
     * @param string $signature The MD5 signature of the encounter
     * @return array The created encounter event data
     */
    private function createNewEncounterEvent($context, $itemSetId, $signature) {


        $encounterData = [
            'o:resource_class' => ['o:id' => 123],
            'o:item_set' => [['o:id' => $itemSetId]],
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => 1,
                    '@value' => $this->generateEncounterTitle($context)
                ]
            ],
            'dcterms:description' => [
                [
                    'type' => 'literal',
                    'property_id' => 4,
                    '@value' => $this->generateEncounterDescription($context)
                ]
            ],
            'dcterms:date' => [
                [
                    'type' => 'literal',
                    'property_id' => 7,
                    '@value' => $context['date']
                ]
            ],
            // Store signature for future lookups
            'dcterms:identifier' => [
                [
                    'type' => 'literal',
                    'property_id' => 10,
                    '@value' => $signature
                ]
            ]
        ];

        // Add context references
        if ($context['context']) {
            $encounterData['excav:foundInContext'] = [
                [
                    'type' => 'literal',
                    'property_id' => 7672,
                    '@value' => $context['context']
                ]
            ];
        }

        if ($context['svu']) {
            $encounterData['excav:foundInSVU'] = [
                [
                    'type' => 'literal',
                    'property_id' => 7671,
                    '@value' => $context['svu']
                ]
            ];
        }

        try {
            $response = $this->api->create('items', $encounterData);
            $encounter = $response->getContent();

            return [
                'id' => $encounter->id(),
                'signature' => $signature,
                'omeka_id' => $encounter->id()
            ];
        } catch (\Exception $e) {

            throw $e;
        }
    }

    /**
     * Generates a title for the encounter event based on the context.
     * @param mixed $ttlData
     * @param mixed $encounterEvent
     * @param mixed $itemSetId
     * @return string
     */
    private function addEncounterEventToTtl($ttlData, $encounterEvent, $itemSetId) {
        $excavationIdentifier = $this->excavationItemSetContextService->getExcavationIdentifierFromItemSet($itemSetId);

        $encounterUri = "{$this->localBaseUriStr}$itemSetId/excavation/$excavationIdentifier/encounter/encounter-{$encounterEvent['omeka_id']}";

        // Extract item identifier and context from TTL
        $itemIdentifier = $this->extractItemIdentifierFromTtl($ttlData);

        $arrowheadContext = $this->extractArrowheadContextFromTtl($ttlData);

        $encounterTriple = "    crmsci:O19i_was_object_encountered_through <$encounterUri> ;\n";

        $pattern = '/(dct:identifier\s+"[^"]+"\^\^xsd:literal\s*;)(\s*)/';
        $replacement = "$1\n$encounterTriple$2";

        $enhancedTtl = preg_replace($pattern, $replacement, $ttlData, 1);

        $escapedLocal = preg_quote($this->localBaseUriStr, '/');
        $selfRefPattern = '/excav:foundInExcavation\s+<' . $escapedLocal . $itemSetId . '\/item\/' . preg_quote($itemIdentifier, '/') . '>\s*;\s*\n/';
        $enhancedTtl = preg_replace($selfRefPattern, '', $enhancedTtl);

        $excavationRefPattern = '/excav:foundInExcavation\s+<' . $escapedLocal . $itemSetId . '\/excavation\/[^>]+>\s*;\s*\n/';
        $enhancedTtl = preg_replace($excavationRefPattern, '', $enhancedTtl);

        $encounterDefinition = "\n\n# =========== ENCOUNTER EVENT ===========\n\n";
        $encounterDefinition .= "<$encounterUri> a excav:EncounterEvent ;\n";

        // Add date if available
        if (!empty($arrowheadContext['date'])) {
            $encounterDefinition .= "    dct:date \"" . $arrowheadContext['date'] . "\"^^xsd:literal ;\n";
        }

        // Add encountered object reference
        $itemUri = "{$this->localBaseUriStr}$itemSetId/item/$itemIdentifier";
        $encounterDefinition .= "    crmsci:O19_encountered_object <$itemUri> ;\n";

        // Add excavation reference
        $excavationUri = "{$this->localBaseUriStr}$itemSetId/excavation/$excavationIdentifier";
        $encounterDefinition .= "    excav:foundInExcavation <$excavationUri> ;\n";

        // Add location reference
        $locationUri = $this->excavationItemSetContextService->getRealLocationUriFromExcavation($itemSetId);
        if ($locationUri && $this->excavationItemSetContextService->locationHasData($itemSetId)) {
            $encounterDefinition .= "    excav:foundInLocation <$locationUri> ;\n";
        }


        if ($arrowheadContext['context']) {
            $contextUri = "{$this->localBaseUriStr}$itemSetId/excavation/$excavationIdentifier/context/{$arrowheadContext['context']}";
            $encounterDefinition .= "    excav:foundInContext <$contextUri> ;\n";
        }

        if ($arrowheadContext['svu']) {
            $svuUri = "{$this->localBaseUriStr}$itemSetId/excavation/$excavationIdentifier/svu/{$arrowheadContext['svu']}";
            $encounterDefinition .= "    excav:foundInSVU <$svuUri> ;\n";
        }

        $encounterDefinition = rtrim($encounterDefinition, " ;\n") . " .\n\n";
        /*
        $encounterDefinition .= "\n# =========== CONTEXT ENTITY DECLARATIONS ===========\n\n";

        $existingDeclarations = $this->checkExistingDeclarations($enhancedTtl, $itemSetId, $excavationIdentifier);

        if (!$existingDeclarations['excavation']) {
            $encounterDefinition .= "<$excavationUri> a excav:Excavation ;\n";
            $encounterDefinition .= "    dct:identifier \"$excavationIdentifier\"^^xsd:literal .\n\n";
        }

        if ($arrowheadContext['context'] && !$existingDeclarations['context']) {
            $contextUri = "{$this->localBaseUriStr}$itemSetId/excavation/$excavationIdentifier/context/{$arrowheadContext['context']}";
            $encounterDefinition .= "<$contextUri> a excav:Context ;\n";
            $encounterDefinition .= "    dct:identifier \"{$arrowheadContext['context']}\"^^xsd:literal .\n\n";
        }

        if ($arrowheadContext['svu'] && !$existingDeclarations['svu']) {
            $svuUri = "{$this->localBaseUriStr}$itemSetId/excavation/$excavationIdentifier/svu/{$arrowheadContext['svu']}";
            $encounterDefinition .= "<$svuUri> a excav:StratigraphicVolumeUnit ;\n";
            $encounterDefinition .= "    dct:identifier \"{$arrowheadContext['svu']}\"^^xsd:literal .\n\n";
        }

        if ($arrowheadContext['location'] && !$existingDeclarations['location']) {
            $locationUri = "{$this->localBaseUriStr}$itemSetId/excavation/$excavationIdentifier/location/{$arrowheadContext['location']}";
            $encounterDefinition .= "<$locationUri> a excav:Location ;\n";
            $encounterDefinition .= "    dct:identifier \"{$arrowheadContext['location']}\"^^xsd:literal .\n\n";
        }

        if ($arrowheadContext['square'] && !$existingDeclarations['square']) {
            $squareUri = "{$this->localBaseUriStr}$itemSetId/excavation/$excavationIdentifier/square/{$arrowheadContext['square']}";
            $encounterDefinition .= "<$squareUri> a excav:Square ;\n";
            $encounterDefinition .= "    dct:identifier \"{$arrowheadContext['square']}\"^^xsd:literal .\n\n";
        }

        return $enhancedTtl . $encounterDefinition;*/

        return $enhancedTtl . $encounterDefinition;
    }
    /**
     * Checks for existing declarations in the TTL data.
     * This method looks for existing context, SVU, square, location, and excavation declarations
     * to avoid duplicates when adding new encounter events.
     * @param string $ttlData The TTL data to check
     * @param int $itemSetId The ID of the item set to check against
     * @param string $excavationIdentifier The excavation identifier to look for
     * @return array An associative array indicating which declarations already exist
     */
    private function checkExistingDeclarations($ttlData, $itemSetId, $excavationIdentifier) {
        $escapedLocal  = preg_quote($this->localBaseUriStr, '/');
        $escapedPublic = preg_quote($this->publicBaseUri, '/');
        $base = '(?:' . $escapedLocal . '|' . $escapedPublic . ')';
        $sid  = preg_quote($itemSetId, '/');
        $eid  = preg_quote($excavationIdentifier, '/');

        $existing = [
            'context' => false,
            'svu' => false,
            'square' => false,
            'location' => false,
            'excavation' => false
        ];

        if (preg_match('/<' . $base . $sid . '\/excavation\/' . $eid . '>\s+a\s+excav:Excavation/', $ttlData)) {
            $existing['excavation'] = true;
        }

        if (preg_match('/<' . $base . $sid . '\/excavation\/' . $eid . '\/context\/[^>]+>\s+a\s+excav:Context/', $ttlData)) {
            $existing['context'] = true;
        }

        if (preg_match('/<' . $base . $sid . '\/excavation\/' . $eid . '\/svu\/[^>]+>\s+a\s+excav:StratigraphicVolumeUnit/', $ttlData)) {
            $existing['svu'] = true;
        }

        if (preg_match('/<' . $base . $sid . '\/excavation\/' . $eid . '\/square\/[^>]+>\s+a\s+excav:Square/', $ttlData)) {
            $existing['square'] = true;
        }

        if (preg_match('/<' . $base . $sid . '\/excavation\/' . $eid . '\/location\/[^>]+>\s+a\s+excav:Location/', $ttlData)) {
            $existing['location'] = true;
        }

        return $existing;
    }

    /**
     * Generates a title for the encounter event based on the context.
     * This method constructs a human-readable title that includes the date, context, and SVU.
     * @param array $context The context data for the encounter
     * @return string The generated title
     */
    private function generateEncounterTitle($context) {
        $parts = [];
        $parts[] = "Archaeological Encounter Event ";
        if ($context['date']) {
            $parts[] = $context['date'];
        }

        if ($context['context'] && $context['svu']) {
            $parts[] = "Context {$context['context']}, SVU {$context['svu']}";
        } elseif ($context['context']) {
            $parts[] = "Context {$context['context']}";
        } elseif ($context['svu']) {
            $parts[] = "SVU {$context['svu']}";
        }

        return implode(' - ', $parts) ?: 'Archaeological Encounter Event';
    }

    /**
     * Generates a description for the encounter event based on the context.
     * This method constructs a detailed description that includes the date, context, SVU, and square.
     * @param array $context The context data for the encounter
     * @return string The generated description
     */
    private function generateEncounterDescription($context) {
        $description = "Archaeological encounter event documenting finds";

        if ($context['date']) {
            $description .= " from " . $context['date'];
        }

        $contextParts = [];
        if ($context['context']) $contextParts[] = "context {$context['context']}";
        if ($context['svu']) $contextParts[] = "stratigraphic unit {$context['svu']}";
        if ($context['square']) $contextParts[] = "square {$context['square']}";

        if (!empty($contextParts)) {
            $description .= " in " . implode(', ', $contextParts);
        }

        return $description . ".";
    }


    /**
     * Extracts the item identifier from the TTL data.
     * This method looks for the dct:identifier property in the TTL data and returns its value.
     * @param string $ttlData The TTL data to extract the identifier from
     * @return string The extracted item identifier or 'unknown-item' if not found
     */
    private function extractItemIdentifierFromTtl($ttlData) {
        if (preg_match('/dct:identifier\s+"([^"]+)"/i', $ttlData, $matches)) {
            return $matches[1];
        }
        return 'unknown-item';
    }

}
