<?php

declare(strict_types=1);

namespace AddTriplestore\Service;

/**
 * SPARQL-driven picklists shared by upload/dashboard views.
 */
final class SiteMetadataOptionsService
{
    /** @var GraphDbHttpService */
    private $graphDbHttpService;

    public function __construct(GraphDbHttpService $graphDbHttpService)
    {
        $this->graphDbHttpService = $graphDbHttpService;
    }

    /** @return list<array{name: string, orcid: string|null}> */
    public function getArchaeologistOptions(): array
    {
        $query = "
    PREFIX foaf: <http://xmlns.com/foaf/0.1/>
    PREFIX excav: <https://purl.org/megalod/ms/excavation/>
    
    SELECT DISTINCT ?name ?orcid
    WHERE {
        ?excavation a excav:Excavation .
        ?excavation excav:hasPersonInCharge ?archaeologist .
        ?archaeologist foaf:name ?name .
        OPTIONAL { 
            ?archaeologist foaf:account ?orcidUri .
            FILTER(CONTAINS(STR(?orcidUri), 'orcid.org'))
            BIND(REPLACE(STR(?orcidUri), '.*/([0-9X-]+)$', '$1') AS ?orcid)
        }
    }
    ORDER BY ?name
    ";

        try {
            $results = $this->graphDbHttpService->postSparqlJson($query);

            $archaeologists = [];
            if (!empty($results['results']['bindings'])) {
                foreach ($results['results']['bindings'] as $result) {
                    $archaeologists[] = [
                        'name' => $result['name']['value'] ?? '',
                        'orcid' => isset($result['orcid']) ? $result['orcid']['value'] : null,
                    ];
                }
            }

            return $archaeologists;
        } catch (\Exception $e) {
            return [];
        }
    }

    /** @return list<string> */
    public function getCountryOptions(): array
    {
        $query = "

    PREFIX dbo: <http://dbpedia.org/ontology/>
PREFIX excav: <https://purl.org/megalod/ms/excavation/>

SELECT DISTINCT ?countryName
WHERE {
  ?location a excav:Location ;
            dbo:Country ?country .
  BIND(REPLACE(STR(?country), 'http://dbpedia.org/resource/', '') AS ?countryName)
}
ORDER BY ?countryName
    ";

        try {
            $results = $this->graphDbHttpService->postSparqlJson($query);

            $countries = [];
            if (!empty($results['results']['bindings'])) {
                foreach ($results['results']['bindings'] as $result) {
                    if (isset($result['countryName'])) {
                        $countries[] = $result['countryName']['value'];
                    }
                }
            }

            return $countries;
        } catch (\Exception $e) {
            return [];
        }
    }

    /** @return list<string> */
    public function getDistrictOptions(): array
    {
        $query = "
    PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX dbo: <http://dbpedia.org/ontology/>

SELECT DISTINCT ?districtName
WHERE {
  ?district rdf:type dbo:District .
  BIND(REPLACE(STR(?district), 'http://dbpedia.org/resource/', '') AS ?districtName)}
    ";

        try {
            $results = $this->graphDbHttpService->postSparqlJson($query);

            $districts = [];
            if (!empty($results['results']['bindings'])) {
                foreach ($results['results']['bindings'] as $result) {
                    if (isset($result['districtName'])) {
                        $districts[] = $result['districtName']['value'];
                    }
                }
            }

            return $districts;
        } catch (\Exception $e) {
            return [];
        }
    }

    /** @return list<string> */
    public function getParishOptions(): array
    {
        $query = "
    PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX dbo: <http://dbpedia.org/ontology/>

SELECT DISTINCT ?parishName
WHERE {
  ?parish rdf:type dbo:Parish .
  BIND(REPLACE(STR(?parish), 'http://dbpedia.org/resource/', '') AS ?parishName)}
    ";

        try {
            $results = $this->graphDbHttpService->postSparqlJson($query);

            $parishes = [];
            if (!empty($results['results']['bindings'])) {
                foreach ($results['results']['bindings'] as $result) {
                    if (isset($result['parishName'])) {
                        $parishes[] = $result['parishName']['value'];
                    }
                }
            }

            return $parishes;
        } catch (\Exception $e) {
            return [];
        }
    }
}
