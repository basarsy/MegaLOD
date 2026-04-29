<?php

namespace AddTriplestore\Service\Ttl;

/**
 * Turtle generation for the collecting / excavation form (excavation, location, squares, contexts, SVUs, timelines).
 */
final class ExcavationTtlBuilder
{
    /** @var TtlUriHelper */
    private $ttlUriHelper;

    /** @var string Public resource base (no trailing slash), e.g. MEGALOD_PUBLIC_BASE_URI */
    private $resourceBaseUri;

    public function __construct(TtlUriHelper $ttlUriHelper, string $megalodPublicBaseUri)
    {
        $this->ttlUriHelper = $ttlUriHelper;
        $this->resourceBaseUri = rtrim($megalodPublicBaseUri, '/');
    }

    /**
     * @param array<string, mixed> $excavationData
     */
    public function buildFromFormData(array $excavationData, ?string $excavationIdentifier): string
    {
        $baseUri = $this->resourceBaseUri;
        $excavationUri = $baseUri . '/excavation/' . ($excavationIdentifier ?? '');

        $hasLocationData = !empty($excavationData['site_name'])
            || !empty($excavationData['district'])
            || !empty($excavationData['parish'])
            || !empty($excavationData['country'])
            || (!empty($excavationData['latitude']) && !empty($excavationData['longitude']));
        $locationUri = null;
        $gpsUri = null;

        if ($hasLocationData) {
            $siteName = $excavationData['site_name'] ?? 'unknown';
            $siteSlug = $this->ttlUriHelper->createUrlSlug($siteName);
            $locationUri = "$baseUri/location/$siteSlug";
            $gpsUri = "$baseUri/gps/$siteSlug";
        }

        $ttl = $this->ttlUriHelper->getTtlPrefixes();

        $ttl .= "# ========================================================================================\n";
        $ttl .= '# EXCAVATION DATA - ' . strtoupper($excavationData['site_name'] ?? 'ARCHAEOLOGICAL SITE') . "\n";
        $ttl .= "# ========================================================================================\n\n";

        $ttl .= "# =========== MAIN EXCAVATION ===========\n\n";

        $ttl .= "<$excavationUri> a excav:Excavation ;\n";

        if ($excavationIdentifier != null) {
            $ttl .= "    dct:identifier \"$excavationIdentifier\"^^xsd:literal ;\n";
        }
        if ($locationUri) {
            $ttl .= "    dul:hasLocation <$locationUri> ;\n";
        }

        if (!empty($excavationData['archaeologist']['name'])) {
            $archaeologistUri = $this->processArchaeologistForTtl($excavationData['archaeologist'], $baseUri);
            if ($archaeologistUri) {
                $ttl .= "    excav:hasPersonInCharge <$archaeologistUri> ;\n";
            }
        }

        if (!empty($excavationData['entities']['squares'])) {
            $squareUris = [];
            foreach ($excavationData['entities']['squares'] as $square) {
                $squareSlug = $this->ttlUriHelper->createUrlSlug($square['square_id']);
                $squareUri = "$baseUri/square/$squareSlug";
                $squareUris[] = "<$squareUri>";
            }
            $ttl .= '    excav:hasSquare ' . implode(",\n                    ", $squareUris) . " ;\n";
        }

        if (!empty($excavationData['entities']['contexts'])) {
            $contextUris = [];
            foreach ($excavationData['entities']['contexts'] as $context) {
                $contextSlug = $this->ttlUriHelper->createUrlSlug($context['context_id']);
                $contextUri = "$baseUri/context/$contextSlug";
                $contextUris[] = "<$contextUri>";
            }
            $ttl .= '    excav:hasContext ' . implode(",\n                     ", $contextUris) . " .\n\n";
        } else {
            $ttl .= "    .\n\n";
        }

        if ($locationUri) {
            $ttl .= "# =========== LOCATION ===========\n\n";
            $locationTtl = $this->generateEnhancedLocationTtl($locationUri, $gpsUri, $excavationData);
            if ($locationTtl) {
                $ttl .= $locationTtl;
            }
        }

        if (!empty($excavationData['archaeologist']['name'])
            && !($excavationData['archaeologist']['existing'] ?? false)) {
            $ttl .= "# =========== ARCHAEOLOGIST ===========\n\n";
            $archaeologistUri = $this->processArchaeologistForTtl($excavationData['archaeologist'], $baseUri);
            $ttl .= $this->generateArchaeologistTtl($archaeologistUri, $excavationData['archaeologist']);
        }

        if (!empty($excavationData['entities']['squares'])) {
            $ttl .= "# =========== EXCAVATION SQUARES ===========\n\n";
            foreach ($excavationData['entities']['squares'] as $square) {
                $squareSlug = $this->ttlUriHelper->createUrlSlug($square['square_id']);
                $squareUri = "$baseUri/square/$squareSlug";
                $ttl .= $this->generateSquareTtl($squareUri, $square);
            }
        }

        if (!empty($excavationData['entities']['contexts'])) {
            $ttl .= "# =========== CONTEXTS ===========\n\n";
            foreach ($excavationData['entities']['contexts'] as $context) {
                $contextSlug = $this->ttlUriHelper->createUrlSlug($context['context_id']);
                $contextUri = "$baseUri/context/$contextSlug";
                $ttl .= $this->generateContextTtl($contextUri, $context, $excavationData['entities'], $baseUri);
            }
        }

        if (!empty($excavationData['entities']['svus'])) {
            $ttl .= "# =========== STRATIGRAPHIC VOLUME UNITS ===========\n\n";
            foreach ($excavationData['entities']['svus'] as $svu) {
                $svuSlug = $this->ttlUriHelper->createUrlSlug($svu['svu_id']);
                $svuUri = "$baseUri/svu/$svuSlug";
                $ttl .= $this->generateSvuTtl($svuUri, $svu);
            }
        }

        $ttl .= $this->buildTimelineAndInstantSections($excavationData, $baseUri);

        return $ttl;
    }

    /**
     * @param array<string, mixed> $archaeologistData
     */
    private function processArchaeologistForTtl(array $archaeologistData, string $baseUri): ?string
    {
        if (($archaeologistData['existing'] ?? false) && !empty($archaeologistData['item_id'])) {
            return "$baseUri/archaeologist/item-" . $archaeologistData['item_id'];
        }
        if (!empty($archaeologistData['name'])) {
            $nameSlug = $this->ttlUriHelper->createUrlSlug($archaeologistData['name']);

            return "$baseUri/archaeologist/$nameSlug";
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $allEntities
     */
    private function generateContextTtl(string $contextUri, array $context, array $allEntities, string $baseUri): string
    {
        $ttl = "<$contextUri> a excav:Context ;\n";
        $ttl .= '    dct:identifier "' . $context['context_id'] . "\"^^xsd:literal ;\n";

        if (!empty($context['context_description'])) {
            $ttl .= '    dct:description "' . $context['context_description'] . "\"^^xsd:literal ;\n";
        }

        if (!empty($allEntities['relationships'])) {
            foreach ($allEntities['relationships'] as $relationship) {
                $contextFound = false;
                foreach ($allEntities['contexts'] as $ctxIndex => $ctx) {
                    if ($ctx['context_id'] === $context['context_id'] && $relationship['context'] == $ctxIndex) {
                        $contextFound = true;
                        break;
                    }
                }

                if ($contextFound && isset($allEntities['svus'][$relationship['svu']])) {
                    $svu = $allEntities['svus'][$relationship['svu']];
                    $svuSlug = $this->ttlUriHelper->createUrlSlug($svu['svu_id']);
                    $svuUri = "$baseUri/svu/$svuSlug";
                    $ttl .= "    excav:hasSVU <$svuUri> ;\n";
                }
            }
        }

        $ttl .= "    .\n\n";

        return $ttl;
    }

    /**
     * @param array<string, mixed> $excavationData
     */
    private function generateEnhancedLocationTtl(string $locationUri, ?string $gpsUri, array $excavationData): ?string
    {
        $ttl = '';

        $hasLocationData = !empty($excavationData['site_name'])
            || !empty($excavationData['district'])
            || !empty($excavationData['parish'])
            || !empty($excavationData['country'])
            || (!empty($excavationData['latitude']) && !empty($excavationData['longitude']));

        if (!$hasLocationData) {
            return null;
        }

        $ttl .= "<$locationUri> a excav:Location ;\n";

        if (!empty($excavationData['site_name'])) {
            $ttl .= '    dbo:informationName "' . $excavationData['site_name'] . "\"^^xsd:literal ;\n";
        }

        $entitiesToDeclare = [];

        if (!empty($excavationData['district'])) {
            $districtSlug = $this->ttlUriHelper->createUrlSlug($excavationData['district']);
            $districtUri = "http://dbpedia.org/resource/$districtSlug";
            $ttl .= "    dbo:district <$districtUri> ;\n";
            $entitiesToDeclare['district'] = [
                'uri' => $districtUri,
                'label' => $excavationData['district'],
            ];
        }

        if (!empty($excavationData['parish'])) {
            $parishSlug = $this->ttlUriHelper->createUrlSlug($excavationData['parish']);
            $parishUri = "http://dbpedia.org/resource/$parishSlug";
            $ttl .= "    dbo:parish <$parishUri> ;\n";
            $entitiesToDeclare['parish'] = [
                'uri' => $parishUri,
                'label' => $excavationData['parish'],
            ];
        }

        if (!empty($excavationData['country'])) {
            $countrySlug = str_replace(' ', '_', $excavationData['country']);
            $countryUri = 'http://dbpedia.org/resource/' . $countrySlug;
            $ttl .= "    dbo:Country <$countryUri> ;\n";
            $entitiesToDeclare['country'] = [
                'uri' => $countryUri,
                'label' => $excavationData['country'],
            ];
        }

        if (!empty($excavationData['latitude']) && !empty($excavationData['longitude']) && $gpsUri) {
            $ttl .= "    excav:hasGPSCoordinates <$gpsUri> ;\n";
        }

        $ttl .= ".\n";
        if (!empty($excavationData['latitude']) && !empty($excavationData['longitude']) && $gpsUri) {
            $ttl .= "<$gpsUri> a excav:GPSCoordinates ;\n";
            $ttl .= '    geo:lat "' . $excavationData['latitude'] . "\"^^xsd:decimal ;\n";
            $ttl .= '    geo:long "' . $excavationData['longitude'] . "\"^^xsd:decimal .\n\n";
        }

        if ($entitiesToDeclare !== []) {
            $ttl .= "# Type declarations for referenced resources\n";

            if (isset($entitiesToDeclare['district'])) {
                $ttl .= "<{$entitiesToDeclare['district']['uri']}> a dbo:District .\n";
            }

            if (isset($entitiesToDeclare['parish'])) {
                $ttl .= "<{$entitiesToDeclare['parish']['uri']}> a dbo:Parish .\n";
            }
            if (isset($entitiesToDeclare['country'])) {
                $ttl .= "<{$entitiesToDeclare['country']['uri']}> a dbo:Country .\n";
            }

            $ttl .= "\n";
        }

        return $ttl;
    }

    /**
     * @param array<string, mixed> $svu
     */
    private function generateSvuTtl(string $svuUri, array $svu): string
    {
        $ttl = "<$svuUri> a excav:StratigraphicVolumeUnit ;\n";
        $ttl .= '    dct:identifier "' . $svu['svu_id'] . "\"^^xsd:literal ;\n";

        if (!empty($svu['svu_description'])) {
            $ttl .= '    dct:description "' . $svu['svu_description'] . "\"^^xsd:literal ;\n";
        }

        if (!empty($svu['svu_lower_year']) || !empty($svu['svu_upper_year'])) {
            $baseUri = dirname(dirname($svuUri));
            $svuSlug = basename($svuUri);
            $timelineUri = "$baseUri/timeline/$svuSlug";

            $ttl .= "    excav:hasTimeline <$timelineUri> ;\n";
        }

        $ttl .= "    .\n\n";

        return $ttl;
    }

    /**
     * @param array<string, mixed> $excavationData
     */
    private function buildTimelineAndInstantSections(array $excavationData, string $baseUri): string
    {
        if (empty($excavationData['entities']['svus'])) {
            return '';
        }

        $append = '';
        $timelineUris = [];
        $instantUris = [];

        foreach ($excavationData['entities']['svus'] as $svu) {
            if (!empty($svu['svu_lower_year']) || !empty($svu['svu_upper_year'])) {
                $svuSlug = $this->ttlUriHelper->createUrlSlug($svu['svu_id']);
                $timelineUri = "$baseUri/timeline/$svuSlug";
                $timelineUris[] = [
                    'uri' => $timelineUri,
                    'svu' => $svu,
                ];
            }
        }

        if ($timelineUris === []) {
            return '';
        }

        $append .= "# =========== TIMELINES ===========\n\n";

        foreach ($timelineUris as $timelineData) {
            $timeline = $timelineData['uri'];
            $svu = $timelineData['svu'];

            $append .= "<$timeline> a excav:TimeLine ;\n";

            if (!empty($svu['svu_lower_year'])) {
                $beginInstantUri = "$timeline/beginning";
                $append .= "    time:hasBeginning <$beginInstantUri> ;\n";
                $instantUris[] = [
                    'uri' => $beginInstantUri,
                    'year' => $svu['svu_lower_year'],
                    'bc' => !empty($svu['svu_lower_bc']),
                ];
            }

            if (!empty($svu['svu_upper_year'])) {
                $endInstantUri = "$timeline/end";
                $append .= "    time:hasEnd <$endInstantUri> .\n\n";
                $instantUris[] = [
                    'uri' => $endInstantUri,
                    'year' => $svu['svu_upper_year'],
                    'bc' => !empty($svu['svu_upper_bc']),
                ];
            } else {
                $append .= "    .\n\n";
            }
        }

        if ($instantUris !== []) {
            $append .= "# =========== TIME INSTANTS ===========\n\n";

            foreach ($instantUris as $instantData) {
                $instantUri = $instantData['uri'];
                $year = $instantData['year'];
                $isBC = $instantData['bc'];

                $append .= "<$instantUri> a excav:Instant ;\n";
                $append .= '    excav:bcad <https://purl.org/megalod/kos/MegaLOD-BCAD/' . ($isBC ? 'BC' : 'AD') . "> ;\n";

                $yearValue = abs((int) $year);
                $yearFormatted = str_pad((string) $yearValue, 4, '0', STR_PAD_LEFT);

                $append .= '    time:inXSDgYear "' . $yearFormatted . "\"^^xsd:gYear .\n\n";
            }
        }

        return $append;
    }

    /**
     * @param array<string, mixed> $archaeologistData
     */
    private function generateArchaeologistTtl(?string $archaeologistUri, array $archaeologistData): string
    {
        if ($archaeologistUri === null || $archaeologistUri === '') {
            return '';
        }

        $ttl = "<$archaeologistUri> a excav:Archaeologist ;\n";

        if (!empty($archaeologistData['name'])) {
            $ttl .= '    foaf:name "' . $archaeologistData['name'] . "\"^^xsd:literal ;\n";
        }

        if (!empty($archaeologistData['orcid'])) {
            $orcidUrl = 'https://orcid.org/' . str_replace('https://orcid.org/', '', $archaeologistData['orcid']);
            $ttl .= "    foaf:account <$orcidUrl> ;\n";
        }

        if (!empty($archaeologistData['email'])) {
            $emails = is_array($archaeologistData['email']) ? $archaeologistData['email'] : [$archaeologistData['email']];
            foreach ($emails as $email) {
                $emailUrl = 'mailto:' . str_replace('mailto:', '', $email);
                $ttl .= "    foaf:mbox <$emailUrl> ;\n";
            }
        }

        $ttl .= "    .\n\n";

        return $ttl;
    }

    /**
     * @param array<string, mixed> $square
     */
    private function generateSquareTtl(string $squareUri, array $square): string
    {
        $ttl = "<$squareUri> a excav:Square ;\n";
        $ttl .= '    dct:identifier "' . $square['square_id'] . "\"^^xsd:literal ;\n";

        if (!empty($square['square_east_west'])) {
            $ttl .= '    geo:lat "' . $square['square_east_west'] . "\"^^xsd:decimal ;\n";
        }

        if (!empty($square['square_north_south'])) {
            $ttl .= '    geo:long "' . $square['square_north_south'] . "\"^^xsd:decimal ;\n";
        }

        $ttl .= "    .\n\n";

        return $ttl;
    }
}
