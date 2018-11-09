<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionDataPayloadField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class VersionDataPayloadFieldSerializer implements FieldSerializerInterface
{
    public function getFieldClass(): string
    {
        return VersionDataPayloadField::class;
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof VersionDataPayloadField) {
            throw new \InvalidArgumentException(
                sprintf('Expected field of type %s got %s', VersionDataPayloadField::class, \get_class($field))
            );
        }
        /* @var VersionDataPayloadField $field */
        yield $field->getStorageName() => $data->getValue();
    }

    public function decode(Field $field, $value)
    {
        if ($value === null) {
            return null;
        }

        return json_decode($value, true);
    }
}
