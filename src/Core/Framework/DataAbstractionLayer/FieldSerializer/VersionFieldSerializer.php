<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Struct\Uuid;

class VersionFieldSerializer implements FieldSerializerInterface
{
    public function getFieldClass(): string
    {
        return VersionField::class;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof VersionField) {
            throw new \InvalidArgumentException(
                sprintf('Expected field of type %s got %s', VersionField::class, \get_class($field))
            );
        }

        $value = $parameters->getContext()->getContext()->getVersionId();

        //write version id of current object to write context
        $parameters->getContext()->set($parameters->getDefinition(), 'versionId', $value);

        /* @var VersionField $field */
        yield $field->getStorageName() => Uuid::fromHexToBytes($value);
    }

    public function decode(Field $field, $value)
    {
        return Uuid::fromBytesToHex($value);
    }
}
