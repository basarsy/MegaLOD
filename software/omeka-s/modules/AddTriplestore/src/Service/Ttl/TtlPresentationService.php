<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Ttl;

use AddTriplestore\Service\GraphDbHttpService;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;


final class TtlPresentationService
{
    /** @var GraphDbHttpService */
    private $graphDbHttpService;

    /** @var TtlUriHelper */
    private $ttlUriHelper;

    /** @var string */
    private $publicBaseUri;

    /** @var string */
    private $localBaseUriStr;

    public function __construct(
        GraphDbHttpService $graphDbHttpService,
        TtlUriHelper $ttlUriHelper,
        string $publicBaseUri,
        string $localBaseUriStr
    ) {
        $this->graphDbHttpService = $graphDbHttpService;
        $this->ttlUriHelper = $ttlUriHelper;
        $this->publicBaseUri = $publicBaseUri;
        $this->localBaseUriStr = $localBaseUriStr;
    }

    public function queryCompleteExcavationFromGraphDB($itemSetId, $resource)
    {
        $graphUri = $this->publicBaseUri . $itemSetId . "/";



        $query = "
        PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
        PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
        PREFIX sh: <http://www.w3.org/ns/shacl#>
        PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
        PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
        PREFIX dct: <http://purl.org/dc/terms/>
        PREFIX foaf: <http://xmlns.com/foaf/0.1/>
        PREFIX dbo: <http://dbpedia.org/ontology/>
        PREFIX crm: <http://www.cidoc-crm.org/cidoc-crm/>
        PREFIX crmsci: <http://cidoc-crm.org/extensions/crmsci/>
        PREFIX crmarchaeo: <http://www.cidoc-crm.org/extensions/crmarchaeo/>
        PREFIX edm: <http://www.europeana.eu/schemas/edm/>
        PREFIX geo: <http://www.w3.org/2003/01/geo/wgs84_pos#>
        PREFIX time: <http://www.w3.org/2006/time#>
        PREFIX schema: <http://schema.org/>
        PREFIX ah: <https://purl.org/megalod/ms/ah/>
        PREFIX excav: <https://purl.org/megalod/ms/excavation/>
        PREFIX dul: <http://www.ontologydesignpatterns.org/ont/dul/DUL.owl#>

        CONSTRUCT {
            ?s ?p ?o .
        }
        WHERE {
            GRAPH <$graphUri> {
                ?s ?p ?o .
            }
        }
        ";

        $ttlData = $this->graphDbHttpService->postConstructAsTurtle($query);

        if ($ttlData) {
            $organizedTtl = $this->organizeAndFormatTtl($ttlData, $itemSetId);


            return $organizedTtl;
        }


        return null;
    }



    /**
     * This method parses the TTL data into subjects and their statements.
     * @param mixed $ttlData
     * @return array<array<array|string|null>>
     */
    private function parseTtlIntoSubjects($ttlData)
    {
        $subjects = [];
        $lines = explode("\n", $ttlData);
        $currentSubject = null;
        $currentStatements = [];
        $inStatement = false;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine) || strpos($trimmedLine, '#') === 0) continue;

            if (preg_match('/^(<[^>]+>)\s+(.+)$/', $trimmedLine, $matches) && !$inStatement) {
                if ($currentSubject && !empty($currentStatements)) {
                    $subjects[$currentSubject] = $this->cleanStatements($currentStatements);
                }
                $currentSubject = $matches[1];
                $currentStatements = [trim($matches[2])];
                $inStatement = (substr($trimmedLine, -1) !== '.');
            } else if ($currentSubject) {
                $currentStatements[] = $trimmedLine;
                if (substr($trimmedLine, -1) === '.') {
                    $subjects[$currentSubject] = $this->cleanStatements($currentStatements);
                    $currentSubject = null;
                    $currentStatements = [];
                    $inStatement = false;
                } else {
                    $inStatement = true;
                }
            }
        }
        if ($currentSubject && !empty($currentStatements)) {
            $subjects[$currentSubject] = $this->cleanStatements($currentStatements);
        }

        return $subjects;
    }

    /**
     * This method cleans up the statements by removing unnecessary whitespace,
     * trailing punctuation, and ensuring consistent formatting.
     * @param array $statements
     * @return array
     */
    private function cleanStatements($statements)
    {
        $cleaned = [];

        foreach ($statements as $statement) {
            $statement = trim($statement);

            $statement = rtrim($statement, ';.');

            $statement = preg_replace('/\s+/', ' ', $statement);

            if (!empty($statement)) {
                $cleaned[] = $statement;
            }
        }

        return $cleaned;
    }
    /**
     * This method formats the subject statements into a readable TTL format.
     * It groups predicates and objects, removes empty lines, and formats the output.
     * @param string $subject The subject URI
     * @param array $statements The statements associated with the subject
     * @return string The formatted TTL string for the subject
     */
    private function formatSubjectStatements($subject, $statements)
    {
        if (empty($statements)) {
            return $subject . " .\n";
        }

        $predicateObjects = [];
        $lastPredicate = null;

        foreach ($statements as $statement) {
            $cleanStatement = trim($statement);
            if (empty($cleanStatement)) continue;
            if (strpos($cleanStatement, 'dct:date') === 0) continue;

            // If line starts with a predicate
            if (preg_match('/^([^\s]+)\s+(.+)$/', $cleanStatement, $matches)) {
                $predicate = $matches[1];
                $object = $matches[2];

                // Filter out foundInSVU and foundInContext for main item
                if (
                    ($predicate === 'excav:foundInSVU' || $predicate === 'excav:foundInContext') &&
                    preg_match('/a\s+(ah:Arrowhead|excav:Item)/', implode(' ', $statements))
                ) {
                    continue;
                }

                $predicateObjects[$predicate][] = $object;
                $lastPredicate = $predicate;
            }
            // If line is just a URI object treat as additional object for previous predicate
            elseif ($lastPredicate && preg_match('/^<[^>]+>$/', $cleanStatement)) {
                $predicateObjects[$lastPredicate][] = $cleanStatement;
            }
        }

        $lines = [];
        foreach ($predicateObjects as $predicate => $objects) {
            // Remove empty and duplicate objects, and filter out empty strings
            $objects = array_filter(array_unique(array_map('trim', $objects)), function($o) {
                return $o !== '' && $o !== ',';
            });
            // Remove any trailing commas from each object
            $objects = array_map(function($o) {
                return rtrim($o, ',');
            }, $objects);
            // Remove any empty objects again after trimming
            $objects = array_filter($objects, function($o) {
                return $o !== '';
            });

            if (count($objects) > 1) {
                $lines[] = "    $predicate " . implode(",\n        ", $objects);
            } elseif (count($objects) === 1) {
                $lines[] = "    $predicate " . reset($objects);
            }
        }

        if (empty($lines)) {
            return $subject . " .\n";
        }

        $ttl = $subject . "\n" . implode(" ;\n", $lines) . " .\n";
        return $ttl;
    }
    /**
     * This method organizes and formats the raw TTL data into a structured format.
     * It groups statements by resource type and adds appropriate headers.
     * @param string $rawTtlData The raw TTL data as a string
     * @param int $itemSetId The ID of the item set for which the TTL is being organized
     * @return string The organized TTL data
     */
    private function organizeAndFormatTtl($rawTtlData, $itemSetId)
    {
        $subjects = $this->parseTtlIntoSubjects($rawTtlData);

        $organizedTtl = $this->ttlUriHelper->getTtlPrefixes();
        $organizedTtl .= "\n# ========================================================================================\n";
        $organizedTtl .= "# ARCHAEOLOGICAL ITEM DATA - ITEM SET $itemSetId\n";
        $organizedTtl .= "# Downloaded from GraphDB on " . date('Y-m-d H:i:s') . "\n";
        $organizedTtl .= "# Organized by resource type for better readability\n";
        $organizedTtl .= "# ========================================================================================\n\n";

        $sections = [
            'excavation' => [
                'title' => 'MAIN EXCAVATION',
                'pattern' => '/a\s+excav:Excavation/'
            ],
            'location' => [
                'title' => 'LOCATION',
                'pattern' => '/a\s+excav:Location/'
            ],
            'gps' => [
                'title' => 'GPS COORDINATES', 
                'pattern' => '/a\s+excav:GPSCoordinates/'
            ],
            'archaeologist' => [
                'title' => 'ARCHAEOLOGIST',
                'pattern' => '/a\s+excav:Archaeologist/'
            ],
            'squares' => [
                'title' => 'EXCAVATION SQUARES',
                'pattern' => '/a\s+excav:Square/'
            ],
            'contexts' => [
                'title' => 'CONTEXTS',
                'pattern' => '/a\s+excav:Context/'
            ],
            'svus' => [
                'title' => 'STRATIGRAPHIC VOLUME UNITS',
                'pattern' => '/a\s+excav:StratigraphicVolumeUnit/'
            ],
            'items' => [
                'title' => 'ARCHAEOLOGICAL ITEMS',
                'pattern' => '/a\s+(ah:Arrowhead|excav:Item)/'
            ],
            'morphology' => [
                'title' => 'MORPHOLOGY',
                'pattern' => '/a\s+ah:Morphology/'
            ],
            'chipping' => [
                'title' => 'CHIPPING',
                'pattern' => '/a\s+ah:Chipping/'
            ],
            'measurements' => [
                'title' => 'MEASUREMENTS',
                'pattern' => '/a\s+(excav:TypometryValue|excav:Weight)/'
            ],
            'coordinates' => [
                'title' => 'COORDINATES',
                'pattern' => '/a\s+excav:Coordinates/'
            ],
            'encounters' => [
                'title' => 'ENCOUNTER EVENTS',
                'pattern' => '/a\s+excav:EncounterEvent/'
            ],
            'timelines' => [
                'title' => 'TIMELINES',
                'pattern' => '/a\s+excav:TimeLine/'
            ],
            'instants' => [
                'title' => 'TIME INSTANTS',
                'pattern' => '/a\s+excav:Instant/'
            ],
            'external' => [
                'title' => 'EXTERNAL REFERENCES',
                'pattern' => '/a\s+(dbo:District|dbo:Parish|dbo:Country)/'
            ]
        ];

        // Process each section
        foreach ($sections as $sectionKey => $sectionInfo) {
            $sectionSubjects = $this->findSubjectsByPattern($subjects, $sectionInfo['pattern']);

            if (!empty($sectionSubjects)) {
                $organizedTtl .= "# =========== {$sectionInfo['title']} ===========\n\n";

                foreach ($sectionSubjects as $subject => $statements) {
                    $organizedTtl .= $this->formatSubjectStatements($subject, $statements);
                    $organizedTtl .= "\n";
                }
            }
        }

        return $organizedTtl;
    }
    /**
     * This method finds subjects in the TTL data that match a specific pattern.
     * It returns an associative array of subjects and their statements that match the pattern.
     * @param array $subjects The parsed subjects from the TTL data
     * @param string $pattern The regex pattern to match against the subject statements
     * @return array An associative array of matching subjects and their statements
     */
    private function findSubjectsByPattern($subjects, $pattern)
    {
        $matchingSubjects = [];

        foreach ($subjects as $subject => $statements) {
            $allStatements = implode(' ', $statements);

            if (preg_match($pattern, $allStatements)) {
                $matchingSubjects[$subject] = $statements;
            }
        }

        return $matchingSubjects;
    }
    /**
     * This method retrieves the TTL prefixes used in the RDF data.
     * It returns a string containing the necessary prefixes for the TTL format.
     * @return string The TTL prefixes
     */
    private function cleanExistingPrefixes($ttlData)
    {
        $lines = explode("\n", $ttlData);
        $cleanedLines = [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if (!empty($trimmedLine) && strpos($trimmedLine, '@prefix') !== 0) {
                $cleanedLines[] = $line;
            }
        }

        return implode("\n", $cleanedLines);
    }


    /**
     * This method queries the GraphDB for a specific item by its ID.
     * @param mixed $resource
     * @param mixed $itemId
     * @return string|null
     */
    public function queryItemFromGraphDB($resource, $itemId)
    {
        $itemSetId = null;

        $itemSets = $resource->itemSets();
        if (!empty($itemSets)) {
            $firstSet = reset($itemSets);
            if ($firstSet) {
                $itemSetId = $firstSet->id();
            }
        }

        if (!$itemSetId) {

            return null;
        }

        $graphUri = "{$this->publicBaseUri}{$itemSetId}/";

        $values = $resource->values();
        $identifier = null;
        if (isset($values['dcterms:identifier'])) {
            $identifier = $values['dcterms:identifier']['values'][0]->value();
        }

        if (!$identifier) {

            return null;
        }

        $itemUriPattern = "{$this->localBaseUriStr}$itemSetId/item/$identifier";

        $query = "
        PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
        PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
        PREFIX sh: <http://www.w3.org/ns/shacl#>
        PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
        PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
        PREFIX dct: <http://purl.org/dc/terms/>
        PREFIX foaf: <http://xmlns.com/foaf/0.1/>
        PREFIX dbo: <http://dbpedia.org/ontology/>
        PREFIX crm: <http://www.cidoc-crm.org/cidoc-crm/>
        PREFIX crmsci: <http://cidoc-crm.org/extensions/crmsci/>
        PREFIX crmarchaeo: <http://www.cidoc-crm.org/extensions/crmarchaeo/>
        PREFIX edm: <http://www.europeana.eu/schemas/edm/>
        PREFIX geo: <http://www.w3.org/2003/01/geo/wgs84_pos#>
        PREFIX time: <http://www.w3.org/2006/time#>
        PREFIX schema: <http://schema.org/>
        PREFIX ah: <https://purl.org/megalod/ms/ah/>
        PREFIX excav: <https://purl.org/megalod/ms/excavation/>
        PREFIX dul: <http://www.ontologydesignpatterns.org/ont/dul/DUL.owl#>

        CONSTRUCT {
            ?s ?p ?o .
            ?related ?relP ?relO .
            ?encounter ?encP ?encO .
        }
        WHERE {
            GRAPH <$graphUri> {
                # Main item and its direct properties
                <$itemUriPattern> ?p ?o .
                BIND(<$itemUriPattern> AS ?s)

                # Get related resources (morphology, chipping, coordinates, etc.)
                OPTIONAL {
                    <$itemUriPattern> ?linkProp ?related .
                    ?related ?relP ?relO .
                    FILTER(STRSTARTS(STR(?related), STR(<$itemUriPattern>)))
                }

                # Get encounter events that reference this item
                OPTIONAL {
                    ?encounter crmsci:O19_encountered_object <$itemUriPattern> .
                    ?encounter ?encP ?encO .
                }

                # Get context resources (location, square, context, svu)
                OPTIONAL {
                    <$itemUriPattern> ?contextProp ?contextRes .
                    ?contextRes ?ctxP ?ctxO .
                    FILTER(?contextProp IN (excav:foundInLocation, excav:foundInSquare, excav:foundInContext, excav:foundInSVU))
                    BIND(?contextRes AS ?s)
                    BIND(?ctxP AS ?p)
                    BIND(?ctxO AS ?o)
                }
            }
        }
        ";

        $rawTtlData = $this->graphDbHttpService->postConstructAsTurtle($query);

        if ($rawTtlData) {
            $organizedTtl = $this->organizeAndFormatItemTtl($rawTtlData, $identifier, $itemSetId);


            return $organizedTtl;
        }

        return null;
    }
    /**
     * This method organizes and formats the raw TTL data for a specific item.
     * It groups statements by resource type and adds appropriate headers.
     * @param string $rawTtlData The raw TTL data as a string
     * @param string $identifier The identifier of the item
     * @param int $itemSetId The ID of the item set for which the TTL is being organized
     * @return string The organized TTL data
     */
    private function organizeAndFormatItemTtl($rawTtlData, $identifier, $itemSetId)
    {
        $subjects = $this->parseTtlIntoSubjects($rawTtlData);

        // Build organized TTL
        $organizedTtl = $this->ttlUriHelper->getTtlPrefixes();
        $organizedTtl .= "\n# ========================================================================================\n";
        $organizedTtl .= "# ARCHAEOLOGICAL ITEM DATA - " . strtoupper($identifier) . "\n";
        $organizedTtl .= "# Downloaded from GraphDB on " . date('Y-m-d H:i:s') . "\n";
        $organizedTtl .= "# Item Set: $itemSetId | Item ID: $identifier\n";
        $organizedTtl .= "# Organized by resource type for better readability\n";
        $organizedTtl .= "# ========================================================================================\n\n";

        $sections = [
            'main_item' => [
                'title' => 'MAIN ARCHAEOLOGICAL ITEM',
                'pattern' => '/(ah:Arrowhead|excav:Item)/'
            ],
            'morphology' => [
                'title' => 'MORPHOLOGY',
                'pattern' => '/ah:Morphology/'
            ],
            'chipping' => [
                'title' => 'CHIPPING',
                'pattern' => '/ah:Chipping/'
            ],
            'typometry' => [
                'title' => 'TYPOMETRY VALUES',
                'pattern' => '/excav:TypometryValue/'
            ],
            'weights' => [
                'title' => 'WEIGHT VALUES',
                'pattern' => '/excav:Weight/'
            ],
            'coordinates' => [
                'title' => 'COORDINATES IN SQUARE',
                'pattern' => '/excav:Coordinates/'
            ],
            'gps' => [
                'title' => 'GPS COORDINATES',
                'pattern' => '/excav:GPSCoordinates/'
            ],
            'encounters' => [
                'title' => 'ENCOUNTER EVENTS',
                'pattern' => '/excav:EncounterEvent/'
            ],
            'excavation' => [
                'title' => 'EXCAVATION REFERENCE',
                'pattern' => '/excav:Excavation/'
            ],
            'location' => [
                'title' => 'LOCATION REFERENCE',
                'pattern' => '/excav:Location/'
            ],
            'squares' => [
                'title' => 'SQUARE REFERENCE',
                'pattern' => '/excav:Square/'
            ],
            'contexts' => [
                'title' => 'CONTEXT REFERENCE',
                'pattern' => '/excav:Context/'
            ],
            'svus' => [
                'title' => 'SVU REFERENCE',
                'pattern' => '/excav:StratigraphicVolumeUnit/'
            ],
            'timelines' => [
                'title' => 'TIMELINE REFERENCE',
                'pattern' => '/excav:TimeLine/'
            ],
            'instants' => [
                'title' => 'TIME INSTANT REFERENCE',
                'pattern' => '/excav:Instant/'
            ],
            'external' => [
                'title' => 'EXTERNAL REFERENCE DECLARATIONS',
                'pattern' => '/(dbo:district|dbo:parish|dbo:Country)/'
            ]
        ];

        // Process each section
        foreach ($sections as $sectionKey => $sectionInfo) {
            $sectionSubjects = $this->findSubjectsByPattern($subjects, $sectionInfo['pattern']);

            if (!empty($sectionSubjects)) {
                $organizedTtl .= "# =========== {$sectionInfo['title']} ===========\n\n";

                if ($sectionKey === 'main_item') {
                    $mainItemUri = "{$this->localBaseUriStr}$itemSetId/item/$identifier";
                    if (isset($sectionSubjects["<$mainItemUri>"])) {
                        $organizedTtl .= $this->formatSubjectStatements("<$mainItemUri>", $sectionSubjects["<$mainItemUri>"]);
                        unset($sectionSubjects["<$mainItemUri>"]);
                        $organizedTtl .= "\n";
                    }
                }

                foreach ($sectionSubjects as $subject => $statements) {
                    $organizedTtl .= $this->formatSubjectStatements($subject, $statements);
                    $organizedTtl .= "\n";
                }

                $organizedTtl .= "\n";
            }
        }



        return $organizedTtl;
    }
}
