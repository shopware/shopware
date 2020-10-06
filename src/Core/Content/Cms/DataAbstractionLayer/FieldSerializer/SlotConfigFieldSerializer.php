<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;

class SlotConfigFieldSerializer extends JsonFieldSerializer
{
    protected function getConstraints(Field $field): array
    {
        return [
            new All([
                'constraints' => new Collection([
                    'allowExtraFields' => false,
                    'allowMissingFields' => false,
                    'fields' => [
                        'source' => [
                            new Choice([
                                'choices' => [
                                    FieldConfig::SOURCE_STATIC,
                                    FieldConfig::SOURCE_MAPPED,
                                    FieldConfig::SOURCE_PRODUCT_STREAM,
                                ],
                            ]),
                            new NotBlank(),
                        ],
                        'value' => [],
                    ],
                ]),
            ]),
        ];
    }
}
