<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Ingestion;

/**
 * Normalize collecting-module POST fields into excavationData for ExcavationTtlBuilder.
 */
final class CollectingFormToExcavationDataMapper
{
    /** @var OmekaResourceLookupService */
    private $omekaLookup;

    public function __construct(OmekaResourceLookupService $omekaLookup)
    {
        $this->omekaLookup = $omekaLookup;
    }

    /** @return array<string, mixed> */
    public function mapFromPost(array $formData): array
    {
        $excavationData = [];

        $fieldMappings = [
            'prompt_32' => 'excavation_id',
            'prompt_35' => 'site_name',
            'prompt_34' => 'parish',
            'prompt_97' => 'district',
            'prompt_51' => 'country',
            'prompt_39' => 'latitude',
            'prompt_40' => 'longitude',
        ];

        foreach ($fieldMappings as $collectingField => $excavationField) {
            if (!empty($formData[$collectingField])) {
                $excavationData[$excavationField] = $formData[$collectingField];
            }
        }

        $excavationData['archaeologist'] = $this->mapArchaeologistFromPost($formData);

        if (!empty($formData['entities_data'])) {
            $entitiesJson = $formData['entities_data'];
            $decoded = json_decode($entitiesJson, true);
            if (is_array($decoded)) {
                $excavationData['entities'] = $decoded;
            }
        }

        return $excavationData;
    }

    /**
     * @param array<string, mixed> $formData
     *
     * @return array{name?: string|null, orcid?: string|null, email?: string|null, existing?: bool, item_id?: mixed}
     */
    private function mapArchaeologistFromPost(array $formData): array
    {
        $archaeologistData = [
            'existing' => false,
            'name' => null,
            'orcid' => null,
            'email' => null,
        ];

        if (!empty($formData['existing_archaeologist'])) {
            $archaeologistData['existing'] = true;
            $archaeologistData['item_id'] = $formData['existing_archaeologist'];

            $archaeologist = $this->omekaLookup->readItem((int) $formData['existing_archaeologist']);
            if ($archaeologist) {
                $values = $archaeologist->values();
                foreach ($values as $term => $propertyValues) {
                    if (!empty($propertyValues) && isset($propertyValues[0])) {
                        $property = $propertyValues[0]->property();
                        if ($property) {
                            $label = $property->label();
                            $value = $propertyValues[0]->value();

                            if (stripos($label, 'name') !== false) {
                                $archaeologistData['name'] = $value;
                            } elseif (stripos($label, 'orcid') !== false || stripos($label, 'account') !== false) {
                                $archaeologistData['orcid'] = str_replace('https://orcid.org/', '', $value);
                            } elseif (stripos($label, 'email') !== false || stripos($label, 'mbox') !== false) {
                                $archaeologistData['email'] = str_replace('mailto:', '', $value);
                            }
                        }
                    }
                }
            }
        } else {
            $archaeologistData['name'] = $formData['new_archaeologist_name'] ?? null;
            $archaeologistData['orcid'] = $formData['new_archaeologist_orcid'] ?? null;
            $archaeologistData['email'] = $formData['new_archaeologist_email'] ?? null;
        }

        return $archaeologistData;
    }
}
