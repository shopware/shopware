<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Doctrine\DBAL\Types\Types;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EnumField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\IsNull;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('framework')]
class EnumFieldSerializer extends AbstractFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        $field = $this->checkFieldTypeOrThrowInvalidFieldException($field);

        // First check for type. Prevents `tryFrom()` to throw an exception
        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $enumCase = null;
        if ($data->getValue() !== null) {
            $enumCase = $field->getEnum()::tryFrom($data->getValue());
        }
        $data->setValue($enumCase?->value);
        $this->validateIfNeeded($field, $existence, $data, $parameters);

        yield $field->getStorageName() => $enumCase?->value;
    }

    public function decode(Field $field, mixed $value): ?\BackedEnum
    {
        $field = $this->checkFieldTypeOrThrowInvalidFieldException($field);
        $fieldValue = $this->castValueToFieldTypeValue($field, $value);

        if ($fieldValue === null) {
            return null;
        }

        return $field->getEnum()::tryFrom($fieldValue);
    }

    /**
     * @param StringField $field
     *
     * @return list<Constraint>
     */
    protected function getConstraints(Field $field): array
    {
        $field = $this->checkFieldTypeOrThrowInvalidFieldException($field);
        $constraints = [
            new AtLeastOneOf([
                new Type($field->getType()),
                new IsNull(),
            ]),
        ];

        if ($field->is(Required::class)) {
            $constraints[] = new NotBlank();
        }

        return $constraints;
    }

    private function checkFieldTypeOrThrowInvalidFieldException(Field $field): EnumField
    {
        if (!$field instanceof EnumField) {
            throw DataAbstractionLayerException::invalidSerializerField(EnumField::class, $field);
        }

        return $field;
    }

    private function castValueToFieldTypeValue(EnumField $field, string|int|null $value): string|int|null
    {
        if ($value === null) {
            return null;
        }

        return match ($field->getType()) {
            Types::STRING => (string) $value,
            Types::INTEGER => $this->castToIntOrThrow($field, $value),
            default => throw DataAbstractionLayerException::fieldHasNoType(static::class),
        };
    }

    private function castToIntOrThrow(EnumField $field, mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        throw DataAbstractionLayerException::expectedFieldValueOfTypeWithValue($field, Types::INTEGER, $value);
    }
}
