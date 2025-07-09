<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\Field;

use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
use Shopware\Core\Content\MeasurementSystem\MeasurementUnitTypeEnum;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('inventory')]
class MeasurementUnitsFieldSerializer extends JsonFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if ($data->getValue() === null) {
            $defaultUnits = MeasurementUnits::createDefaultUnits();

            $data->setValue([
                'system' => $defaultUnits->getSystem(),
                'units' => $defaultUnits->getUnits(),
            ]);
        } elseif ($data->getValue() instanceof MeasurementUnits) {
            $measurementUnits = $data->getValue();

            $data->setValue([
                'system' => $measurementUnits->getSystem(),
                'units' => $measurementUnits->getUnits(),
            ]);
        }

        yield from parent::encode($field, $existence, $data, $parameters);
    }

    public function decode(Field $field, mixed $value): MeasurementUnits
    {
        $defaultUnits = MeasurementUnits::createDefaultUnits();

        if ($value === null) {
            return $defaultUnits;
        }

        $decoded = parent::decode($field, $value);
        if (!\is_array($decoded)) {
            return $defaultUnits;
        }
        $system = $decoded['system'] ?? $defaultUnits->getSystem();
        $units = !empty($decoded['units']) ? array_merge($defaultUnits->getUnits(), $decoded['units']) : $defaultUnits->getUnits();

        return new MeasurementUnits($system, $units);
    }

    protected function getConstraints(Field $field): array
    {
        return [
            new Type('array'),
            new NotNull(),
            new Collection([
                'allowExtraFields' => true,
                'allowMissingFields' => false,
                'fields' => [
                    'system' => [new NotBlank(), new Type('string')],
                    'units' => [
                        new Type('array'),
                        new Collection([
                            'allowExtraFields' => true,
                            'allowMissingFields' => false,
                            'fields' => [
                                MeasurementUnitTypeEnum::LENGTH->value => [new Type('string'), new NotNull()],
                                MeasurementUnitTypeEnum::WEIGHT->value => [new Type('string'), new NotNull()],
                            ],
                        ]),
                    ],
                ],
            ]),
        ];
    }
}
