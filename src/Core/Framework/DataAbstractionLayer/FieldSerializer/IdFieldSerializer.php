<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Struct\Uuid;

class IdFieldSerializer implements FieldSerializerInterface
{
    public function getFieldClass(): string
    {
        return IdField::class;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof IdField) {
            throw new InvalidSerializerFieldException(IdField::class, $field);
        }
        $value = $data->getValue();
        if (!$value) {
            $value = Uuid::uuid4()->getHex();
        }

        $parameters->getContext()->set($parameters->getDefinition(), $data->getKey(), $value);

        /* @var IdField $field */
        yield $field->getStorageName() => Uuid::fromStringToBytes($value);
    }

    public function decode(Field $field, $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Uuid::fromBytesToHex($value);
    }
}
