<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class UpdatedAtFieldSerializer extends DateFieldSerializer
{
    public function getFieldClass(): string
    {
        return UpdatedAtField::class;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof UpdatedAtField) {
            throw new InvalidSerializerFieldException(UpdatedAtField::class, $field);
        }
        if (!$existence->exists()) {
            return;
        }

        $value = new KeyValuePair($data->getKey(), new \DateTime(), $data->isRaw());

        yield from parent::encode($field, $existence, $value, $parameters);
    }
}
