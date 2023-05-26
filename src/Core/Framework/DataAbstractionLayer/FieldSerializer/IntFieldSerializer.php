<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('core')]
class IntFieldSerializer extends AbstractFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof IntField) {
            throw DataAbstractionLayerException::invalidSerializerField(IntField::class, $field);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        yield $field->getStorageName() => $data->getValue();
    }

    public function decode(Field $field, mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    /**
     * @param IntField $field
     *
     * @return Constraint[]
     */
    protected function getConstraints(Field $field): array
    {
        $constraints = [
            new Type('int'),
            new NotBlank(),
        ];

        if ($field->getMinValue() !== null || $field->getMaxValue() !== null) {
            $constraints[] = new Range(['min' => $field->getMinValue(), 'max' => $field->getMaxValue()]);
        }

        return $constraints;
    }
}
