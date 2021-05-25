<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\Uuid as UuidConstraint;

class IdFieldSerializer extends AbstractFieldSerializer
{
    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        $key = $field->getPropertyName();
        if (!isset($data[$key])) {
            $data[$key] = Uuid::randomHex();
        }

        $parameters->getContext()->set($parameters->getDefinition()->getClass(), $key, $data[$key]);

        return $data;
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
        if ($value) {
            $this->validate([new UuidConstraint()], $data, $parameters->getPath());
        } else {
            $value = Uuid::randomHex();
        }

        $parameters->getContext()->set($parameters->getDefinition()->getClass(), $data->getKey(), $value);

        yield $field->getStorageName() => Uuid::fromHexToBytes($value);
    }

    /**
     * @deprecated tag:v6.5.0 The parameter $value will be native typed
     */
    public function decode(Field $field, /*?string */$value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Uuid::fromBytesToHex($value);
    }
}
