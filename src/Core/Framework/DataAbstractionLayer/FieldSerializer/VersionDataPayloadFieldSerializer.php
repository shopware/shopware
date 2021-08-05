<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionDataPayloadField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class VersionDataPayloadFieldSerializer implements FieldSerializerInterface
{
    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        return $data;
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof VersionDataPayloadField) {
            throw new InvalidSerializerFieldException(VersionDataPayloadField::class, $field);
        }

        yield $field->getStorageName() => $data->getValue();
    }

    /**
     * @return mixed|null
     *
     * @deprecated tag:v6.5.0 The parameter $value will be native typed
     */
    public function decode(Field $field, /*?string */$value)
    {
        if ($value === null) {
            return null;
        }

        return json_decode($value, true);
    }
}
