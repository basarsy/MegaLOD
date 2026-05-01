<?php

namespace AddTriplestore\Service\Ttl;

/**
 * Reads Turtle from a PHP http file upload (TTL or XML→TTL via {@see XmlToTtlPipeline}).
 */
final class UploadedFileToTtlConverter
{
    /** @var XmlToTtlPipeline */
    private $xmlToTtlPipeline;

    public function __construct(XmlToTtlPipeline $xmlToTtlPipeline)
    {
        $this->xmlToTtlPipeline = $xmlToTtlPipeline;
    }

    /**
     * Align reported MIME type with extension (browsers vary on .ttl).
     *
     * @return non-empty-string One of application/x-turtle, application/xml, text/xml
     */
    public function resolveNormalizedMime(string $originalName, string $declaredMime): string
    {
        $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = $declaredMime;
        if ($ext === 'ttl' && $mime !== 'application/x-turtle') {
            $mime = 'application/x-turtle';
        }

        return $mime;
    }

    public function isAllowedUploadMime(string $mime): bool
    {
        return in_array($mime, ['application/x-turtle', 'application/xml', 'text/xml'], true);
    }

    /**
     * @param array{name?:mixed,tmp_name?:mixed,type?:mixed} $file $_FILES["file"] style
     *
     * @throws \Exception on conversion failure
     */
    public function extractTurtleFromPhpUpload(array $file): string
    {
        $mime = isset($file['type']) ? (string) $file['type'] : '';
        $name = isset($file['name']) ? (string) $file['name'] : '';
        $mime = $this->resolveNormalizedMime($name, $mime);

        if ($mime === 'application/xml' || $mime === 'text/xml') {
            $rdfXmlData = $this->xmlToTtlPipeline->parseUploadedXmlToRdfXml($file);
            if (is_string($rdfXmlData) && strpos($rdfXmlData, '<?xml') !== false) {
                return $this->xmlToTtlPipeline->rdfXmlToTurtle($rdfXmlData);
            }
            throw new \Exception('Failed to process XML file: ' . (is_string($rdfXmlData) ? $rdfXmlData : 'invalid response'));
        }

        $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        if ($tmp === '' || !is_readable($tmp)) {
            throw new \Exception('No file uploaded or file upload error.');
        }

        $contents = file_get_contents($tmp);
        if ($contents === false) {
            throw new \Exception('Failed to read uploaded file.');
        }

        return $contents;
    }
}
