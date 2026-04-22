<?php

namespace AddTriplestore\Service;

/**
 * Connection and URI settings for MegaLOD / GraphDB (site controller and services).
 */
final class MegalodConfig
{
    /** @var string */
    private $graphdbBaseUrl;

    /** @var string */
    private $graphdbRepository;

    /** @var string */
    private $megalodPublicBaseUri;

    /** @var string */
    private $megalodLocalBaseUri;

    /** @var string */
    private $graphdbWorkbenchUrl;

    public function __construct(
        string $graphdbBaseUrl,
        string $graphdbRepository,
        string $megalodPublicBaseUri,
        string $megalodLocalBaseUri,
        string $graphdbWorkbenchUrl
    ) {
        $this->graphdbBaseUrl = $graphdbBaseUrl;
        $this->graphdbRepository = $graphdbRepository;
        $this->megalodPublicBaseUri = $megalodPublicBaseUri;
        $this->megalodLocalBaseUri = $megalodLocalBaseUri;
        $this->graphdbWorkbenchUrl = $graphdbWorkbenchUrl;
    }

    public function getGraphdbBaseUrl(): string
    {
        return $this->graphdbBaseUrl;
    }

    public function getGraphdbRepository(): string
    {
        return $this->graphdbRepository;
    }

    public function getMegalodPublicBaseUri(): string
    {
        return $this->megalodPublicBaseUri;
    }

    public function getMegalodLocalBaseUri(): string
    {
        return $this->megalodLocalBaseUri;
    }

    public function getGraphdbWorkbenchUrl(): string
    {
        return $this->graphdbWorkbenchUrl;
    }

    public function getGraphdbRdfGraphsServiceUrl(): string
    {
        return $this->graphdbBaseUrl . '/repositories/' . $this->graphdbRepository . '/rdf-graphs/service';
    }

    public function getGraphdbQueryEndpoint(): string
    {
        return $this->graphdbBaseUrl . '/repositories/' . $this->graphdbRepository;
    }
}
