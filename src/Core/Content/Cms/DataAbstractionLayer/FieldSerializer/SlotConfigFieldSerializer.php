<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Content\Cms\DataAbstractionLayer\Field\SlotConfigField;
use Shopware\Core\Content\Cms\SlotDataResolver\FieldConfig;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldValidatorTrait;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;

class SlotConfigFieldSerializer extends JsonFieldSerializer
{
    use FieldValidatorTrait;

    public function getFieldClass(): string
    {
        return SlotConfigField::class;
    }

    protected function getConstraints(WriteParameterBag $parameters): array
    {
        return [
            new All([
                'constraints' => new Collection([
                    'allowExtraFields' => false,
                    'allowMissingFields' => false,
                    'fields' => [
                        'source' => [
                            new Choice(['choices' => [FieldConfig::SOURCE_STATIC, FieldConfig::SOURCE_MAPPED]]),
                            new NotBlank(),
                        ],
                        'value' => [
                            new NotBlank(),
                        ],
                    ],
                ]),
            ]),
        ];
    }
}
