<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
class BoolFieldSerializer extends AbstractFieldSerializer
{
    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof BoolField) {
            throw new InvalidSerializerFieldException(BoolField::class, $field);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        if ($data->getValue() === null) {
            yield $field->getStorageName() => null;

            return;
        }

        yield $field->getStorageName() => $data->getValue() ? 1 : 0;
    }

    public function decode(Field $field, $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }

    protected function getConstraints(Field $field): array
    {
        return [
            new Type('bool'),
        ];
    }
}
