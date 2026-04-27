<?php

namespace AddTriplestore\Service\Ttl;

/**
 * XML (XSLT) → RDF/XML → Turtle pipeline for AddTriplestore uploads.
 */
final class XmlToTtlPipeline
{
    /** @var TtlUriHelper */
    private $ttlUriHelper;

    public function __construct(TtlUriHelper $ttlUriHelper)
    {
        $this->ttlUriHelper = $ttlUriHelper;
    }

    /**
     * @param array<string, mixed> $file Uploaded file array (tmp_name, etc.)
     * @return string|false RDF/XML string or error message
     */
    public function parseUploadedXmlToRdfXml(array $file)
    {
        $xmlContent = file_get_contents($file['tmp_name']);

        if (strpos($xmlContent, '<item id="AH') !== false
            || strpos($xmlContent, 'arrowhead') !== false
            || strpos($xmlContent, '<ah:') !== false) {
            $xsltPath = OMEKA_PATH . '/modules/AddTriplestore/asset/xlst/arrowXslt.xml';
        } elseif (strpos($xmlContent, '<Excavation') !== false
            || strpos($xmlContent, 'excavation') !== false
            || strpos($xmlContent, '<excav:') !== false) {
            $xsltPath = OMEKA_PATH . '/modules/AddTriplestore/asset/xlst/excavationXslt.xml';
        } else {
            return 'Could not determine XML type';
        }

        if (!file_exists($xsltPath)) {
            return 'XSLT file not found';
        }

        $xslt = new \DOMDocument();
        $xslt->load($xsltPath);

        $xmlDoc = new \DOMDocument();
        if (!$xmlDoc->load($file['tmp_name'])) {
            return 'Failed to load XML file';
        }

        $processor = new \XSLTProcessor();
        $processor->importStylesheet($xslt);
        $rdfXmlConverted = $processor->transformToXML($xmlDoc);

        if (!$rdfXmlConverted) {
            return 'Failed to convert XML to RDF-XML';
        }

        return $rdfXmlConverted;
    }

    public function rdfXmlToTurtle(string $rdfXmlData): string
    {
        \EasyRdf\RdfNamespace::set('dct', 'http://purl.org/dc/terms/');
        \EasyRdf\RdfNamespace::set('ah', 'https://purl.org/megalod/ms/ah/');
        \EasyRdf\RdfNamespace::set('excav', 'https://purl.org/megalod/ms/excavation/');
        \EasyRdf\RdfNamespace::set('dul', 'http://www.ontologydesignpatterns.org/ont/dul/DUL.owl#');

        $cleanedRdfXml = $this->cleanRdfXmlNamespaces($rdfXmlData);
        $rdfGraph = new \EasyRdf\Graph();
        $rdfGraph->parse($cleanedRdfXml, 'rdfxml');
        $ttlData = $rdfGraph->serialise('turtle');

        return $this->cleanupTtlOutput($ttlData);
    }

    private function cleanRdfXmlNamespaces(string $rdfXmlData): string
    {
        $namespaces = [
            'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'sh' => 'http://www.w3.org/ns/shacl#',
            'xsd' => 'http://www.w3.org/2001/XMLSchema#',
            'skos' => 'http://www.w3.org/2004/02/skos/core#',
            'dct' => 'http://purl.org/dc/terms/',
            'foaf' => 'http://xmlns.com/foaf/0.1/',
            'dbo' => 'http://dbpedia.org/ontology/',
            'crm' => 'http://www.cidoc-crm.org/cidoc-crm/',
            'crmsci' => 'http://cidoc-crm.org/extensions/crmsci/',
            'crmarchaeo' => 'http://www.cidoc-crm.org/extensions/crmarchaeo/',
            'edm' => 'http://www.europeana.eu/schemas/edm/',
            'geo' => 'http://www.w3.org/2003/01/geo/wgs84_pos#',
            'time' => 'http://www.w3.org/2006/time#',
            'schema' => 'http://schema.org/',
            'ah' => 'https://purl.org/megalod/ms/ah/',
            'excav' => 'https://purl.org/megalod/ms/excavation/',
            'dul' => 'http://www.ontologydesignpatterns.org/ont/dul/DUL.owl#',
        ];

        $dom = new \DOMDocument();
        $dom->loadXML($rdfXmlData);
        $root = $dom->documentElement;
        if ($root) {
            foreach ($namespaces as $prefix => $namespace) {
                $root->setAttributeNS('http://www.w3.org/2000/xmlns/', "xmlns:$prefix", $namespace);
            }
        }

        return $dom->saveXML();
    }

    private function cleanupTtlOutput(string $ttlData): string
    {
        $cleanTtl = $this->ttlUriHelper->getTtlPrefixes();
        $lines = explode("\n", $ttlData);
        $contentLines = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (!empty($trimmed) && strpos($trimmed, '@prefix') !== 0) {
                $contentLines[] = $line;
            }
        }
        $content = implode("\n", $contentLines);
        $content = $this->replaceNamespacePrefixes($content);
        $content = $this->applyTtlFormatting($content);

        return $cleanTtl . "\n" . $content;
    }

    private function replaceNamespacePrefixes(string $content): string
    {
        if (strpos($content, 'ah:Arrowhead') !== false || strpos($content, 'excav:Item') !== false) {
            $replacements = [
                '/ns0:/' => 'edm:',
                '/ns1:/' => 'dbo:',
                '/ns2:/' => 'crm:',
            ];
        } else {
            $replacements = [
                '/ns0:/' => 'dbo:',
            ];
        }

        foreach ($replacements as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        $uriReplacements = [
            '<https://purl.org/megalod/ms/ah/' => '<ah:',
            '<https://purl.org/megalod/ms/excavation/' => '<excav:',
            '<http://purl.org/dc/terms/' => '<dct:',
            '<http://xmlns.com/foaf/0.1/' => '<foaf:',
            '<http://dbpedia.org/ontology/' => '<dbo:',
            '<http://www.cidoc-crm.org/cidoc-crm/' => '<crm:',
            '<http://cidoc-crm.org/extensions/crmsci/' => '<crmsci:',
            '<http://www.europeana.eu/schemas/edm/' => '<edm:',
            '<http://www.w3.org/2003/01/geo/wgs84_pos#' => '<geo:',
            '<http://www.w3.org/2006/time#' => '<time:',
            '<http://schema.org/' => '<schema:',
            '<http://www.ontologydesignpatterns.org/ont/dul/DUL.owl#' => '<dul:',
            '<http://www.w3.org/1999/02/22-rdf-syntax-ns#' => '<rdf:',
            '<http://www.w3.org/2001/XMLSchema#' => '<xsd:',
        ];

        foreach ($uriReplacements as $uri => $prefix) {
            $content = str_replace($uri, $prefix, $content);
        }

        $content = preg_replace('/dc:identifier/', 'dct:identifier', $content);
        $content = preg_replace('/dc:date/', 'dct:date', $content);
        $content = preg_replace('/dc:description/', 'dct:description', $content);

        return $content;
    }

    private function applyTtlFormatting(string $content): string
    {
        $content = preg_replace('/"true"\^\^xsd:boolean/', 'true', $content);
        $content = preg_replace('/"false"\^\^xsd:boolean/', 'false', $content);
        $content = preg_replace('/"\^\^xsd:literal/', '"^^xsd:literal', $content);
        $content = $this->fixKosUris($content);
        $content = preg_replace_callback(
            '/time:inXSDgYear "(-?\d+)"\^\^xsd:gYear/',
            function ($matches) {
                $year = $matches[1];
                if (strpos($year, '-') === 0) {
                    $year = str_replace('-', '', $year);
                    $year = '-' . str_pad($year, 4, '0', STR_PAD_LEFT);
                } else {
                    $year = str_pad($year, 4, '0', STR_PAD_LEFT);
                }

                return 'time:inXSDgYear "' . $year . '"^^xsd:gYear';
            },
            $content
        );
        $content = str_replace('>', '>', $content);

        return $content;
    }

    private function fixKosUris(string $content): string
    {
        $kosPatterns = [
            '/ah-shape:(\w+)/' => '<https://purl.org/megalod/kos/ah-shape/$1>',
            '/ah-variant:(\w+)/' => '<https://purl.org/megalod/kos/ah-variant/$1>',
            '/ah-base:(\w+)/' => '<https://purl.org/megalod/kos/ah-base/$1>',
            '/ah-chippingMode:(\w+)/' => '<https://purl.org/megalod/kos/ah-chippingMode/$1>',
            '/ah-chippingDirection:(\w+)/' => '<https://purl.org/megalod/kos/ah-chippingDirection/$1>',
            '/ah-chippingDelineation:(\w+)/' => '<https://purl.org/megalod/kos/ah-chippingDelineation/$1>',
            '/ah-chippingLocation:(\w+)/' => '<https://purl.org/megalod/kos/ah-chippingLocation/$1>',
            '/ah-chippingShape:(\w+)/' => '<https://purl.org/megalod/kos/ah-chippingShape/$1>',
            '/MegaLOD-IndexElongation:(\w+)/' => '<https://purl.org/megalod/kos/MegaLOD-IndexElongation/$1>',
            '/MegaLOD-IndexThickness:(\w+)/' => '<https://purl.org/megalod/kos/MegaLOD-IndexThickness/$1>',
            '/MegaLOD-BCAD:(\w+)/' => '<https://purl.org/megalod/kos/MegaLOD-BCAD/$1>',
        ];

        foreach ($kosPatterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        return $content;
    }
}
