<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class StringFieldSerializer extends AbstractFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof StringField) {
            throw new InvalidSerializerFieldException(StringField::class, $field);
        }

        if ($data->getValue() === '') {
            $data->setValue(null);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        /* @var LongTextField $field */
        yield $field->getStorageName() => $data->getValue() !== null ? strip_tags((string) $data->getValue()) : null;
    }

    public function decode(Field $field, $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    /**
     * @param StringField $field
     */
    protected function getConstraints(Field $field): array
    {
        return [
            new NotBlank(),
            new Type('string'),
            new Length(['max' => $field->getMaxLength()]),
        ];
    }
}
