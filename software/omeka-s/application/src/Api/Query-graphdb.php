<?php
namespace Omeka\Api;
// Set response headers
header('Content-Type: application/json');

// Check if a query parameter is provided
if (!isset($_GET['query'])) {
    echo json_encode(['error' => 'Missing query parameter']);
    exit;
}

// Get the SPARQL query from the request
$sparqlQuery = $_GET['query'];

// GraphDB SPARQL endpoint — resolved from environment
$graphdbBaseUrl = getenv('GRAPHDB_BASE_URL') ?: null;
if (empty($graphdbBaseUrl)) {
    $host = getenv('GRAPHDB_HOST');
    $port = getenv('GRAPHDB_PORT');
    if ($host && $port) {
        $graphdbBaseUrl = "http://$host:$port";
    }
}
$graphdbRepo = getenv('GRAPHDB_REPOSITORY') ?: null;
if (empty($graphdbBaseUrl) || empty($graphdbRepo)) {
    echo json_encode(['error' => 'GraphDB endpoint not configured. Set GRAPHDB_BASE_URL and GRAPHDB_REPOSITORY env vars.']);
    exit;
}
$endpoint = rtrim($graphdbBaseUrl, '/') . '/repositories/' . $graphdbRepo;

// Initialize cURL
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $endpoint . '?query=' . urlencode($sparqlQuery));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/sparql-results+json',
]);

// Execute the query
$response = curl_exec($ch);

// Handle errors
if (curl_errno($ch)) {
    echo json_encode(['error' => curl_error($ch)]);
    curl_close($ch);
    exit;
}

// Close cURL
curl_close($ch);

// Return the response
echo $response;
