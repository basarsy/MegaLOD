<?php

namespace AddTriplestore\Service\Ingestion;

use AddTriplestore\Service\ExcavationItemSetContextService;

/**
 * Maps collecting-form prompt_* fields to the normalized arrowhead field map consumed by ArrowheadTtlBuilder.
 */
final class CollectingFormToArrowheadMapper
{
    /** @var ExcavationItemSetContextService */
    private $excavationContext;

    /** @var string */
    private $localBaseUri;

    public function __construct(ExcavationItemSetContextService $excavationContext, string $megalodLocalBaseUri)
    {
        $this->excavationContext = $excavationContext;
        $this->localBaseUri = $megalodLocalBaseUri;
    }

    /**
     * @param array<string, mixed> $formData
     *
     * @return array<string, mixed>
     */
    public function map(array $formData): array
    {
        $arrowheadData = [];

        $fieldMappings = [
            'prompt_53' => 'arrowhead_identifier',
            'prompt_54' => 'images',
            'prompt_55' => 'arrowhead_annotation',
            'prompt_56' => 'condition_state',
            'prompt_57' => 'weight',
            'prompt_58' => 'weight_unit',
            'prompt_59' => 'height',
            'prompt_60' => 'height_unit',
            'prompt_61' => 'width',
            'prompt_62' => 'width_unit',
            'prompt_63' => 'thickness',
            'prompt_64' => 'thickness_unit',
            'prompt_65' => 'arrowhead_type',
            'prompt_66' => 'elongation_index',
            'prompt_100' => 'thickness_index',
            'prompt_67' => 'gps_latitude',
            'prompt_68' => 'gps_longitude',
            'prompt_69' => 'arrowhead_variant',
            'prompt_70' => 'arrowhead_shape',
            'prompt_71' => 'point_definition',
            'prompt_72' => 'body_symmetry',
            'prompt_73' => 'arrowhead_base',
            'prompt_74' => 'body_length',
            'prompt_75' => 'body_length_unit',
            'prompt_76' => 'base_length',
            'prompt_77' => 'base_length_unit',
            'prompt_78' => 'chipping_mode',
            'prompt_79' => 'chipping_amplitude',
            'prompt_80' => 'chipping_direction',
            'prompt_81' => 'chipping_orientation',
            'prompt_82' => 'chipping_delineation',
            'prompt_83' => 'chipping_location_lateral_1',
            'prompt_84' => 'chipping_location_lateral_2',
            'prompt_85' => 'chipping_location_lateral_3',
            'prompt_86' => 'chipping_location_transversal_1',
            'prompt_87' => 'chipping_location_transversal_2',
            'prompt_88' => 'chipping_location_transversal_3',
            'prompt_89' => 'chipping_shape',
            'prompt_90' => 'x_coordinate',
            'prompt_91' => 'y_coordinate',
            'prompt_92' => 'z_coordinate',
            'prompt_93' => 'arrowhead_material',
            'prompt_94' => 'x_coordinate_unit',
            'prompt_95' => 'y_coordinate_unit',
            'prompt_96' => 'z_coordinate_unit',
            'prompt_99' => 'encounter_date',
        ];

        foreach ($fieldMappings as $collectingField => $arrowheadField) {
            if (isset($formData[$collectingField]) && !empty($formData[$collectingField])) {
                $value = $formData[$collectingField];

                if (strpos($value, 'True') === 0 || strpos($value, 'true') === 0) {
                    $arrowheadData[$arrowheadField] = 'true';
                } elseif (strpos($value, 'False') === 0 || strpos($value, 'false') === 0) {
                    $arrowheadData[$arrowheadField] = 'false';
                } else {
                    $cleanValue = preg_replace('/\s*\([^)]*\)/', '', $value);
                    $arrowheadData[$arrowheadField] = trim($cleanValue);
                }
            }
        }

        if (!empty($formData['selected_square'])) {
            $arrowheadData['selected_square'] = $formData['selected_square'];
        }

        if (!empty($formData['selected_context'])) {
            $arrowheadData['selected_context'] = $formData['selected_context'];
        }

        if (!empty($formData['selected_svu'])) {
            $arrowheadData['selected_svu'] = $formData['selected_svu'];
        }

        if (isset($formData['file']['54']) && is_array($formData['file']['54'])) {
            $imageFiles = $formData['file']['54'];
            $imageUrls = [];
            $baseUrl = "{$this->localBaseUri}images/";

            foreach ($imageFiles as $imageFile) {
                if (!empty($imageFile)) {
                    $filename = basename($imageFile);
                    $imageUrls[] = $baseUrl . $filename;
                }
            }

            if (!empty($imageUrls)) {
                $arrowheadData['images'] = $imageUrls;
            }
        }

        $itemSetId = isset($formData['item_set_id']) ? $formData['item_set_id'] : null;

        if ($itemSetId) {
            try {
                $locationUri = $this->excavationContext->getExcavationLocationUri(null, $itemSetId);

                if ($locationUri) {
                    $arrowheadData['location'] = $locationUri;
                }
            } catch (\Exception $e) {
            }
        }

        $valuesToCheck = [
            'thickness' => 'thickness_unit',
            'body_length' => 'body_length_unit',
            'base_length' => 'base_length_unit',
        ];

        foreach ($valuesToCheck as $valueField => $unitField) {
            if (empty($arrowheadData[$valueField]) && isset($arrowheadData[$unitField])) {
                unset($arrowheadData[$unitField]);
            }
        }

        return $arrowheadData;
    }
}
