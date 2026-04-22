<?php

namespace AddTriplestore\Service\Ttl;

/**
 * Stateless Turtle / URI string helpers used when building or fixing TTL.
 */
final class TtlUriHelper
{
    /**
     * RDF prefix block for generated Turtle files.
     */
    public function getTtlPrefixes(): string
    {
        return "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n" .
            "@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .\n" .
            "@prefix sh: <http://www.w3.org/ns/shacl#> .\n" .
            "@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .\n" .
            "@prefix skos: <http://www.w3.org/2004/02/skos/core#> .\n" .
            "@prefix dct: <http://purl.org/dc/terms/> .\n" .
            "@prefix foaf: <http://xmlns.com/foaf/0.1/> .\n" .
            "@prefix dbo: <http://dbpedia.org/ontology/> .\n" .
            "@prefix crm: <http://www.cidoc-crm.org/cidoc-crm/> .\n" .
            "@prefix crmsci: <http://cidoc-crm.org/extensions/crmsci/> .\n" .
            "@prefix crmarchaeo: <http://www.cidoc-crm.org/extensions/crmarchaeo/> .\n" .
            "@prefix edm: <http://www.europeana.eu/schemas/edm/> .\n" .
            "@prefix geo: <http://www.w3.org/2003/01/geo/wgs84_pos#> .\n" .
            "@prefix time: <http://www.w3.org/2006/time#> .\n" .
            "@prefix schema: <http://schema.org/> .\n" .
            "@prefix ah: <https://purl.org/megalod/ms/ah/> .\n" .
            "@prefix excav: <https://purl.org/megalod/ms/excavation/> .\n" .
            "@prefix dul: <http://www.ontologydesignpatterns.org/ont/dul/DUL.owl#> .\n";
    }

    /**
     * @param mixed $value
     */
    public function sanitizeForUri($value): string
    {
        $value = (string) $value;
        if (preg_match('/^([^(]+)/', $value, $matches)) {
            $value = trim($matches[1]);
        }

        $value = preg_replace('/[\s()]+/', '', $value);

        return $value;
    }

    /**
     * @param mixed $string
     */
    public function createUrlSlug($string): string
    {
        $slug = strtolower((string) $string);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        if (empty($slug)) {
            $slug = 'unknown';
        }

        return $slug;
    }

    /**
     * @param mixed $filename
     */
    public function sanitizeFilenameForUri($filename): string
    {
        $filename = (string) $filename;
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '-', $basename);
        $basename = preg_replace('/-+/', '-', $basename);
        $basename = trim($basename, '-');

        if (empty($basename)) {
            $basename = 'file';
        }

        if (!empty($extension)) {
            return $basename . '.' . $extension;
        }

        return $basename;
    }

    /**
     * @param mixed $filename
     */
    public function sanitizeFilename($filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $filename);
        $filename = trim($filename, '_');
        if (empty($filename)) {
            $filename = 'download';
        }

        return $filename;
    }
}
