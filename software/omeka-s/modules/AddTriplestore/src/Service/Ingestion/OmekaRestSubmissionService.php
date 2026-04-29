<?php

namespace AddTriplestore\Service\Ingestion;

use AddTriplestore\Service\OmekaApiCredentialService;
use Laminas\Http\Client;
use Omeka\Api\Manager as ApiManager;

/**
 * REST item creation, batch duplicate / existence checks, media upload, and item-set metadata patches.
 * Keeps Omeka write orchestration out of the site controller (SRP).
 *
 * Skip-if-exists uses OmekaResourceLookupService::itemExistsWithDctermsIdentifierInItemSet() (property 10, exact eq),
 * not findItemByIdentifier(), so behavior matches the pre-refactor controller (no title fallback or identifier variations).
 */
final class OmekaRestSubmissionService
{
    /** @var OmekaApiCredentialService */
    private $credentials;

    /** @var OmekaResourceLookupService */
    private $resourceLookup;

    /** @var ApiManager */
    private $api;

    public function __construct(
        OmekaApiCredentialService $credentials,
        OmekaResourceLookupService $resourceLookup,
        ApiManager $api
    ) {
        $this->credentials = $credentials;
        $this->resourceLookup = $resourceLookup;
        $this->api = $api;
    }

    /**
     * @param list<array<string, mixed>> $omekaData
     * @param array<string, mixed>|null $uploadedFiles Multi-file $_FILES shape (name/type/tmp_name/error/size arrays)
     * @param array<string, mixed>|null $excavationData Optional keys: location, archaeologist (for item set update)
     *
     * @return array{
     *     errors: list<string>,
     *     created_items: list<array<string, mixed>>,
     *     skipped_items: list<array<string, mixed>>
     * }
     */
    public function submitItemPayloads(
        array $omekaData,
        ?int $itemSetId = null,
        ?array $uploadedFiles = null,
        ?array $excavationData = null
    ): array {
        $omekaApi = $this->credentials->getApiCredentials();
        $omekaBaseUrl = $omekaApi['base_url'];
        $omekaKeyIdentity = $omekaApi['key_identity'];
        $omekaKeyCredential = $omekaApi['key_credential'];

        $client = new Client();
        $client->setMethod('POST');
        $client->setHeaders([
            'Content-Type' => 'application/json',
        ]);

        $errors = [];
        $createdItems = [];
        $skippedItems = [];

        $identifierMap = [];
        $duplicatesInBatch = [];

        foreach ($omekaData as $itemIndex => $itemData) {
            $identifier = OmekaItemPayloadSupport::extractDctermsIdentifier($itemData);
            if ($identifier !== null && $identifier !== '') {
                if (isset($identifierMap[$identifier])) {
                    $duplicatesInBatch[] = $identifier;
                    $errors[] = "Duplicate identifier '$identifier' found in the current batch (items {$identifierMap[$identifier]} and $itemIndex)";
                } else {
                    $identifierMap[$identifier] = $itemIndex;
                }
            }
        }

        foreach ($omekaData as $itemIndex => $itemData) {
            $identifier = OmekaItemPayloadSupport::extractDctermsIdentifier($itemData);

            if ($identifier !== null && $identifier !== '' && in_array($identifier, $duplicatesInBatch, true)) {
                $skippedItems[] = [
                    'index' => $itemIndex,
                    'identifier' => $identifier,
                    'reason' => 'Duplicate identifier in current batch',
                ];
                continue;
            }

            if ($identifier !== null && $identifier !== '' && $itemSetId !== null
                && $this->resourceLookup->itemExistsWithDctermsIdentifierInItemSet($identifier, $itemSetId)) {
                $skippedItems[] = [
                    'index' => $itemIndex,
                    'identifier' => $identifier,
                    'reason' => 'Item with this identifier already exists in the item set',
                ];
                $errors[] = "Skipped item $itemIndex: An item with identifier '$identifier' already exists in item set #$itemSetId";
                continue;
            }

            $fullUrl = rtrim($omekaBaseUrl, '/') . '/items'
                . '?key_identity=' . urlencode($omekaKeyIdentity)
                . '&key_credential=' . urlencode($omekaKeyCredential);

            $client->setUri($fullUrl);
            $client->setRawBody(json_encode($itemData));
            $response = $client->send();

            if (!$response->isSuccess()) {
                $errors[] = 'Failed to create item ' . ($itemIndex + 1) . ': '
                    . $response->getStatusCode() . ' - ' . $response->getBody();
                continue;
            }

            $createdItem = json_decode($response->getBody(), true);
            if (!is_array($createdItem) || !isset($createdItem['o:id'])) {
                $errors[] = 'Failed to parse created item response for item ' . ($itemIndex + 1);
                continue;
            }

            $itemId = (int) $createdItem['o:id'];
            $this->attachUploadedMediaToItem($itemId, $uploadedFiles);
            $createdItems[] = $createdItem;
        }

        if ($itemSetId !== null && $createdItems !== [] && !empty($excavationData)) {
            $this->updateItemSetWithExcavationInfo($itemSetId, $excavationData);
        }

        return [
            'errors' => $errors,
            'created_items' => $createdItems,
            'skipped_items' => $skippedItems,
        ];
    }

    /**
     * @param array<string, mixed> $excavationData
     */
    private function updateItemSetWithExcavationInfo(int $itemSetId, array $excavationData): bool
    {
        if ($itemSetId <= 0 || empty($excavationData)) {
            return false;
        }

        try {
            $updateData = [];

            if (!empty($excavationData['location'])) {
                $updateData['dcterms:description'] = [
                    [
                        'type' => 'literal',
                        'property_id' => 4,
                        '@value' => 'Archaeological excavation at ' . $excavationData['location'],
                    ],
                ];
            }

            if (!empty($excavationData['archaeologist'])) {
                $updateData['dcterms:creator'] = [
                    [
                        'type' => 'literal',
                        'property_id' => 7,
                        '@value' => $excavationData['archaeologist'],
                    ],
                ];
            }

            if ($updateData !== []) {
                $updateResult = $this->api->update(
                    'item_sets',
                    $itemSetId,
                    $updateData,
                    [],
                    ['isPartial' => true]
                );

                return (bool) $updateResult;
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed>|null $uploadedFiles
     */
    private function attachUploadedMediaToItem(int $itemId, ?array $uploadedFiles): void
    {
        if (!$uploadedFiles || !isset($uploadedFiles['name']) || !is_array($uploadedFiles['name'])) {
            return;
        }

        for ($i = 0; $i < count($uploadedFiles['name']); $i++) {
            if (($uploadedFiles['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $tempFile = $uploadedFiles['tmp_name'][$i];
            $filename = $uploadedFiles['name'][$i];
            $mimeType = $uploadedFiles['type'][$i];

            try {
                $mediaData = [
                    'o:ingester' => 'upload',
                    'o:item' => ['o:id' => $itemId],
                    'dcterms:title' => [
                        [
                            'type' => 'literal',
                            'property_id' => 1,
                            '@value' => $filename,
                        ],
                    ],
                ];

                $tempDir = sys_get_temp_dir();
                $targetPath = $tempDir . '/' . uniqid('omeka_upload_', true) . '_' . basename($filename);
                if (!copy($tempFile, $targetPath)) {
                    continue;
                }

                $_FILES = [
                    'file' => [
                        'name' => [$filename],
                        'type' => [$mimeType],
                        'tmp_name' => [$targetPath],
                        'error' => [0],
                        'size' => [filesize($targetPath) ?: 0],
                    ],
                ];

                $this->api->create('media', $mediaData);
            } catch (\Exception $e) {
                // Preserve prior behavior: failures are swallowed here
            }
        }
    }
}
