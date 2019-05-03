<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Uuid\Uuid;

class VersionFieldSerializer implements FieldSerializerInterface
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof VersionField) {
            throw new InvalidSerializerFieldException(VersionField::class, $field);
        }

        $value = $data->getValue();
        if ($value === null) {
            $value = $parameters->getContext()->getContext()->getVersionId();
        }

        //write version id of current object to write context
        $parameters->getContext()->set($parameters->getDefinition()->getClass(), 'versionId', $value);

        /* @var VersionField $field */
        yield $field->getStorageName() => Uuid::fromHexToBytes($value);
    }

    public function decode(Field $field, $value): string
    {
        return Uuid::fromBytesToHex($value);
    }
}
