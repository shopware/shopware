<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\Field;

use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;

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
            // Use default values when null
            $data->setValue([
                'system' => MeasurementUnits::DEFAULT_MEASUREMENT_SYSTEM,
                'units' => [
                    'length' => MeasurementUnits::DEFAULT_LENGTH_UNIT,
                    'weight' => MeasurementUnits::DEFAULT_WEIGHT_UNIT,
                ],
            ]);
        } elseif ($data->getValue() instanceof MeasurementUnits) {
            /** @var MeasurementUnits $measurementUnits */
            $measurementUnits = $data->getValue();
            
            // Convert MeasurementUnits to array
            $data->setValue([
                'system' => $measurementUnits->getSystem(),
                'units' => $measurementUnits->getUnits(),
            ]);
        }

        yield from parent::encode($field, $existence, $data, $parameters);
    }

    public function decode(Field $field, mixed $value): MeasurementUnits
    {
        if ($value === null) {
            return MeasurementUnits::createDefaultUnits();
        }

        $decoded = parent::decode($field, $value);
        if (!\is_array($decoded)) {
            return MeasurementUnits::createDefaultUnits();
        }

        $system = $decoded['system'] ?? MeasurementUnits::DEFAULT_MEASUREMENT_SYSTEM;
        $units = $decoded['units'] ?? [
            'length' => MeasurementUnits::DEFAULT_LENGTH_UNIT,
            'weight' => MeasurementUnits::DEFAULT_WEIGHT_UNIT,
        ];

        return new MeasurementUnits($system, $units);
    }
} 