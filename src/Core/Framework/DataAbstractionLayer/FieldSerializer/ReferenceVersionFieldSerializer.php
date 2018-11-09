<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Struct\Uuid;

class ReferenceVersionFieldSerializer implements FieldSerializerInterface
{
    public function getFieldClass(): string
    {
        return ReferenceVersionField::class;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof ReferenceVersionField) {
            throw new \InvalidArgumentException(
                sprintf('Expected field of type %s got %s', ReferenceVersionField::class, \get_class($field))
            );
        }
        /** @var ReferenceVersionField $field */
        if ($parameters->getDefinition() === $field->getVersionReference()) {
            //parent inheritance with versioning
            $value = $data->getValue() ?? Defaults::LIVE_VERSION;
        } elseif ($parameters->getContext()->has($field->getVersionReference(), 'versionId')) {
            $value = $parameters->getContext()->get($field->getVersionReference(), 'versionId');
        } else {
            $value = Defaults::LIVE_VERSION;
        }

        yield $field->getStorageName() => Uuid::fromStringToBytes($value);
    }

    public function decode(Field $field, $value)
    {
        if ($value === null) {
            return null;
        }

        return Uuid::fromBytesToHex($value);
    }
}
