<?php

namespace AddTriplestore\Service;

use Laminas\Http\Client;
use Laminas\Log\Logger;
use Laminas\Log\Writer\Stream;

/**
 * GraphDB HTTP operations used by the site controller (SHACL check, Turtle upload, SPARQL JSON).
 */
final class GraphDbHttpService
{
    /** @var MegalodConfig */
    private $megalodConfig;

    /** @var GraphDbCredentialService */
    private $credentials;

    public function __construct(MegalodConfig $megalodConfig, GraphDbCredentialService $credentials)
    {
        $this->megalodConfig = $megalodConfig;
        $this->credentials = $credentials;
    }

    /**
     * Run SHACL validation query against a named graph, then POST Turtle if valid.
     * Matches legacy IndexController::sendToGraphDB behavior and messages.
     */
    public function uploadAfterShaclValidation(string $graphUri, string $turtleBody): string
    {
        $logger = $this->createGraphDbLogger();

        try {
            $validationResult = $this->validateShaclForNamedGraph($graphUri);
            if (!empty($validationResult)) {
                $errorMessage = 'Data upload failed: SHACL validation errors: ' . implode('; ', $validationResult);
                $logger->err($errorMessage);

                return $errorMessage;
            }

            $creds = $this->credentials->getWriteCredentials();
            $client = new Client();
            $fullUrl = $this->megalodConfig->getGraphdbRdfGraphsServiceUrl() . '?graph=' . urlencode($graphUri);
            $client->setUri($fullUrl);
            $client->setMethod('POST');
            $client->setHeaders([
                'Content-Type' => 'text/turtle',
                'Authorization' => 'Basic ' . base64_encode($creds['username'] . ':' . $creds['password']),
            ]);
            $client->setRawBody($turtleBody);
            $client->setOptions(['timeout' => 60]);

            $response = $client->send();
            $status = $response->getStatusCode();
            $body = $response->getBody();
            $message = "Response Status: $status | Response Body: $body";
            $logger->info($message);

            if ($status == 401) {
                $errorMessage = 'Authentication failed with GraphDB. Please check your credentials.';
                $logger->err($errorMessage);

                return $errorMessage;
            }

            if ($response->isSuccess()) {
                return 'Data uploaded and validated successfully.';
            }

            $errorMessage = 'Failed to upload data: ' . $message;
            $logger->err($errorMessage);

            return $errorMessage;
        } catch (\Exception $e) {
            $errorMessage = 'Failed to upload data due to an exception: ' . $e->getMessage();
            $logger->err($errorMessage);

            return $errorMessage;
        }
    }

    /**
     * SHACL validation via SPARQL (legacy validateData; $turtleBody was unused).
     *
     * @return string[] Error messages; empty if none
     */
    public function validateShaclForNamedGraph(string $graphUri): array
    {
        $errors = [];
        $logger = $this->createGraphDbLogger();

        try {
            $creds = $this->credentials->getWriteCredentials();

            $query = "PREFIX sh: <http://www.w3.org/ns/shacl#>
            PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
            
            SELECT ?message
            WHERE {
              GRAPH <http://rdf4j.org/schema/rdf4j#SHACLShapeGraph> {
                ?shape a sh:NodeShape .
              }
              GRAPH <$graphUri> {
                ?focusNode ?predicate ?object .
              }
              FILTER EXISTS {
                  GRAPH <http://rdf4j.org/schema/rdf4j#SHACLShapeGraph> {
                    ?shape sh:targetClass ?targetClass .
                    FILTER NOT EXISTS { ?focusNode a ?targetClass }
                  }
              }
              FILTER EXISTS {
                  GRAPH <http://rdf4j.org/schema/rdf4j#SHACLShapeGraph> {
                    ?shape sh:property ?propertyShape .
                    ?propertyShape sh:path ?path .
                    FILTER NOT EXISTS { ?focusNode ?path ?object }
                  }
              }
              BIND(CONCAT('Violation at node: ', str(?focusNode), ', predicate: ', str(?predicate), ', object: ', str(?object)) AS ?message)
            }
            ";

            $client = new Client();
            $client->setUri($this->megalodConfig->getGraphdbQueryEndpoint());
            $client->setMethod('POST');
            $client->setHeaders([
                'Content-Type' => 'application/sparql-query',
                'Accept' => 'application/sparql-results+json',
                'Authorization' => 'Basic ' . base64_encode($creds['username'] . ':' . $creds['password']),
            ]);
            $client->setRawBody($query);
            $response = $client->send();
            if ($response->getStatusCode() == 401) {
                $errorMessage = 'Authentication failed with GraphDB. Please check your credentials.';
                $logger->err($errorMessage);

                return [$errorMessage];
            }

            if (!$response->isSuccess()) {
                $errorMessage = 'SHACL validation query failed: ' . $response->getStatusCode() . ' - ' . $response->getBody();
                $logger->err($errorMessage);

                return [$errorMessage];
            }

            $rawBody = $response->getBody();
            $results = json_decode($rawBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $errorMessage = 'Error decoding JSON response: ' . json_last_error_msg() . ' Raw Body: ' . $rawBody;
                $logger->err($errorMessage);

                return [$errorMessage];
            }

            if (isset($results['results']['bindings'])) {
                foreach ($results['results']['bindings'] as $binding) {
                    $errors[] = $binding['message']['value'];
                }
            }
        } catch (\Exception $e) {
            $errorMessage = 'SHACL validation failed due to an exception: ' . $e->getMessage();
            $logger->err($errorMessage);

            return [$errorMessage];
        }

        return $errors;
    }

    /**
     * Authenticated SPARQL query returning JSON results (legacy executeGraphDbQuery).
     *
     * @return array|null Decoded JSON body on success
     */
    public function postSparqlJson(string $sparql): ?array
    {
        try {
            $client = new Client();
            $client->setUri($this->megalodConfig->getGraphdbQueryEndpoint());
            $client->setMethod('POST');

            $creds = $this->credentials->getWriteCredentials();
            $client->setHeaders([
                'Content-Type' => 'application/sparql-query',
                'Accept' => 'application/sparql-results+json',
                'Authorization' => 'Basic ' . base64_encode($creds['username'] . ':' . $creds['password']),
            ]);

            $client->setRawBody($sparql);

            $response = $client->send();

            if ($response->isSuccess()) {
                $results = json_decode($response->getBody(), true);

                return $results;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function createGraphDbLogger(): Logger
    {
        $logger = new Logger();
        $logger->addWriter(new Stream(OMEKA_PATH . '/logs/graphdb-errors.log'));

        return $logger;
    }
}
