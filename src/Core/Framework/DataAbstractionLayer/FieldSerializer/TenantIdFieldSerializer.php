<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TenantIdField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Struct\Uuid;

class TenantIdFieldSerializer implements FieldSerializerInterface
{
    public function getFieldClass(): string
    {
        return TenantIdField::class;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof TenantIdField) {
            throw new \InvalidArgumentException(
                sprintf('Expected field of type %s got %s', TenantIdField::class, \get_class($field))
            );
        }

        $value = $parameters->getContext()->getContext()->getTenantId();

        /* @var TenantIdField $field */
        yield $field->getStorageName() => Uuid::fromStringToBytes($value);
    }

    public function decode(Field $field, $value)
    {
        return Uuid::fromBytesToHex($value);
    }
}
