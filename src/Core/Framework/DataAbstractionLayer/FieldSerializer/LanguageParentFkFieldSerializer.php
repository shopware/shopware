<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LanguageParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Struct\Uuid;

class LanguageParentFkFieldSerializer implements FieldSerializerInterface
{
    public function getFieldClass(): string
    {
        return LanguageParentFkField::class;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof LanguageParentFkField) {
            throw new InvalidSerializerFieldException(LanguageParentFkField::class, $field);
        }
        $context = $parameters->getContext();

        $currentLanguageId = $parameters->getCurrentWriteLanguageId();
        if ($context->isRootLanguage($currentLanguageId)) {
            yield $field->getStorageName() => null;
        } else {
            yield $field->getStorageName() => Uuid::fromHexToBytes($context->getRootLanguageId($currentLanguageId));
        }
    }

    public function decode(Field $field, $value)
    {
        if ($value === null) {
            return null;
        }

        return Uuid::fromBytesToHex($value);
    }
}
