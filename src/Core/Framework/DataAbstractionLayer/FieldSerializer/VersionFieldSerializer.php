<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
class VersionFieldSerializer implements FieldSerializerInterface
{
    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        $value = $data[$field->getPropertyName()] ?? null;
        if ($value === null) {
            $value = $parameters->getContext()->getContext()->getVersionId();
        }

        //write version id of current object to write context
        $parameters->getContext()->set($parameters->getDefinition()->getEntityName(), 'versionId', $value);

        $data[$field->getPropertyName()] = $value;

        return $data;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof VersionField) {
            throw new InvalidSerializerFieldException(VersionField::class, $field);
        }

        if ($data->getValue() === null) {
            $result = $this->normalize($field, [$field->getPropertyName() => $data->getValue()], $parameters);
            $data->setValue($result[$field->getPropertyName()]);
        }

        yield $field->getStorageName() => Uuid::fromHexToBytes($data->getValue());
    }

    /**
     * @param string $value
     *
     * @deprecated tag:v6.5.0 - reason:return-type-change - The return type will change to ?string
     */
    public function decode(Field $field, $value): string
    {
        return Uuid::fromBytesToHex($value);
    }
}
