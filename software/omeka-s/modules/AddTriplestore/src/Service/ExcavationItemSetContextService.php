<?php

namespace AddTriplestore\Service;

use Omeka\Api\Manager as ApiManager;
use Omeka\Settings\SiteSettings;

/**
 * Excavation identifiers and location resolution scoped to the current site (settings + GraphDB + Omeka API).
 */
final class ExcavationItemSetContextService
{
    /** @var SiteSettings */
    private $siteSettings;

    /** @var ApiManager */
    private $apiManager;

    /** @var GraphDbHttpService */
    private $graphDbHttpService;

    /** @var string */
    private $publicBaseUri;

    /** @var string */
    private $localBaseUri;

    public function __construct(
        SiteSettings $siteSettings,
        ApiManager $apiManager,
        GraphDbHttpService $graphDbHttpService,
        MegalodConfig $megalodConfig
    ) {
        $this->siteSettings = $siteSettings;
        $this->apiManager = $apiManager;
        $this->graphDbHttpService = $graphDbHttpService;
        $this->publicBaseUri = $megalodConfig->getMegalodPublicBaseUri();
        $this->localBaseUri = $megalodConfig->getMegalodLocalBaseUri();
    }

    public function storeMappingBetweenItemSetAndExcavation($itemSetId, $excavationId): void
    {
        $mappings = $this->siteSettings->get('excavation_itemset_mappings', []);
        $mappings[$itemSetId] = $excavationId;
        $this->siteSettings->set('excavation_itemset_mappings', $mappings);
    }

    /**
     * @return string|null
     */
    public function getExcavationIdentifierFromItemSet($itemSetId)
    {
        $mappings = $this->siteSettings->get('excavation_itemset_mappings', []);

        if (isset($mappings[$itemSetId])) {
            return $mappings[$itemSetId];
        }

        try {
            $itemSet = $this->apiManager->read('item_sets', $itemSetId)->getContent();
            $title = $itemSet->displayTitle();

            if (preg_match('/Excavation\s+([^\s]+)/', $title, $matches)) {
                $excavationId = $matches[1];
                $this->storeMappingBetweenItemSetAndExcavation($itemSetId, $excavationId);

                return $excavationId;
            }
        } catch (\Exception $e) {
        }

        return null;
    }

    /**
     * Stable placeholder URI used by collecting-form image URLs (not necessarily Graph-backed).
     *
     * @param mixed $excavationId unused legacy param; kept for call compatibility
     * @param mixed $itemSetId
     */
    public function getExcavationLocationUri($excavationId, $itemSetId = null): string
    {
        $baseId = $itemSetId ?: $excavationId;

        return "{$this->localBaseUri}{$baseId}/location/excavation-location";
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLocationDataFromExcavation($itemSetId)
    {
        try {
            $graphUri = $this->publicBaseUri . $itemSetId . '/';

            $query = '
        PREFIX excav: <https://purl.org/megalod/ms/excavation/>
        PREFIX dbo: <http://dbpedia.org/ontology/>
        PREFIX geo: <http://www.w3.org/2003/01/geo/wgs84_pos#>
        PREFIX dul: <http://www.ontologydesignpatterns.org/ont/dul/DUL.owl#>

        SELECT ?locationUri ?locationName ?district ?parish ?country ?lat ?long
        WHERE {
          GRAPH <' . $graphUri . '> {
            ?excavation a excav:Excavation ;
                        dul:hasLocation ?locationUri .

            OPTIONAL { ?locationUri dbo:informationName ?locationName }
            OPTIONAL { ?locationUri dbo:district ?district }
            OPTIONAL { ?locationUri dbo:parish ?parish }
            OPTIONAL { ?locationUri dbo:Country ?country }
            OPTIONAL { ?locationUri geo:lat ?lat }
            OPTIONAL { ?locationUri geo:long ?long }
          }
        }
        LIMIT 1';

            $results = $this->graphDbHttpService->selectSparqlBindings($query);

            if (!empty($results)) {
                $result = $results[0];

                return [
                    'uri' => $result['locationUri']['value'],
                    'name' => isset($result['locationName']) ? $result['locationName']['value'] : null,
                    'district' => isset($result['district']) ? $result['district']['value'] : null,
                    'parish' => isset($result['parish']) ? $result['parish']['value'] : null,
                    'country' => isset($result['country']) ? $result['country']['value'] : null,
                    'lat' => isset($result['lat']) ? $result['lat']['value'] : null,
                    'long' => isset($result['long']) ? $result['long']['value'] : null,
                ];
            }
        } catch (\Exception $e) {
        }

        return null;
    }

    /**
     * @return string|null
     */
    public function getRealLocationUriFromExcavation($itemSetId)
    {
        if (!$itemSetId) {
            return null;
        }

        $excavationIdentifier = $this->getExcavationIdentifierFromItemSet($itemSetId);
        if (!$excavationIdentifier) {
            return null;
        }

        $locationData = $this->getLocationDataFromExcavation($itemSetId);
        if (empty($locationData) || (
            empty($locationData['name'])
            && empty($locationData['district'])
            && empty($locationData['parish'])
            && empty($locationData['country'])
            && empty($locationData['lat'])
            && empty($locationData['long'])
        )) {
            return null;
        }

        $graphUri = $this->publicBaseUri . $itemSetId . '/';
        $locationQuery = "
        PREFIX excav: <https://purl.org/megalod/ms/excavation/>
        PREFIX dul: <http://www.ontologydesignpatterns.org/ont/dul/DUL.owl#>

        SELECT ?locationUri
        WHERE {
            GRAPH <$graphUri> {
                ?excavation a excav:Excavation ;
                           dul:hasLocation ?locationUri .
            }
        }
        LIMIT 1
    ";

        try {
            $results = $this->graphDbHttpService->selectSparqlBindings($locationQuery);

            if (!empty($results) && isset($results[0]['locationUri'])) {
                return $results[0]['locationUri']['value'];
            }
        } catch (\Exception $e) {
        }

        return "{$this->localBaseUri}{$itemSetId}/excavation/{$excavationIdentifier}/location/excavation-location";
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?array}
     */
    public function resolveForArrowheadTtl(string $itemSetId): array
    {
        $excavationIdentifier = $this->getExcavationIdentifierFromItemSet($itemSetId);
        $realLocationUri = $this->getRealLocationUriFromExcavation($itemSetId);
        $locationData = $realLocationUri ? $this->getLocationDataFromExcavation($itemSetId) : null;

        return [$excavationIdentifier, $realLocationUri, $locationData];
    }

    public function locationHasData($itemSetId): bool
    {
        $locationData = $this->getLocationDataFromExcavation($itemSetId);

        return !empty($locationData) && (
            !empty($locationData['name'])
            || !empty($locationData['district'])
            || !empty($locationData['parish'])
            || !empty($locationData['country'])
            || !empty($locationData['lat'])
            || !empty($locationData['long'])
        );
    }
}
