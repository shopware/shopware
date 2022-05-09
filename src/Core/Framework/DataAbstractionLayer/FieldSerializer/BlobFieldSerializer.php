<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
class BlobFieldSerializer implements FieldSerializerInterface
{
    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        return $data;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof BlobField) {
            throw new InvalidSerializerFieldException(BlobField::class, $field);
        }

        yield $field->getStorageName() => $data->getValue();
    }

    /**
     * @return string|null
     *
     * @deprecated tag:v6.5.0 - reason:return-type-change - return type will be native typed
     */
    public function decode(Field $field, $value)/*: ?string*/
    {
        if ($value === null) {
            return $value;
        }

        return (string) $value;
    }
}
