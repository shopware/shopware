<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class ConfigJsonFieldSerializer extends JsonFieldSerializer
{
    public function getFieldClass(): string
    {
        return ConfigJsonField::class;
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof ConfigJsonField) {
            throw new InvalidSerializerFieldException(ConfigJsonField::class, $field);
        }

        $wrapped = [ConfigJsonField::STORAGE_KEY => $data->getValue()];
        $data = new KeyValuePair($data->getKey(), $wrapped, $data->isRaw());

        return parent::encode($field, $existence, $data, $parameters);
    }

    public function decode(Field $field, $value)
    {
        if (!$field instanceof ConfigJsonField) {
            throw new InvalidSerializerFieldException(ConfigJsonField::class, $field);
        }

        $wrapped = parent::decode($field, $value);
        if ($wrapped === null || !isset($wrapped[ConfigJsonField::STORAGE_KEY])) {
            return null;
        }

        return $wrapped[ConfigJsonField::STORAGE_KEY];
    }
}
