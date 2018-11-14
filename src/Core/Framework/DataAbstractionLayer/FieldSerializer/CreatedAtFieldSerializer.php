<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class CreatedAtFieldSerializer extends DateFieldSerializer
{
    public function getFieldClass(): string
    {
        return CreatedAtField::class;
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if ($existence->exists()) {
            return;
        }

        if (!$data->getValue()) {
            $data = new KeyValuePair($data->getKey(), new \DateTime(), $data->isRaw());
        }

        yield from parent::encode($field, $existence, $data, $parameters);
    }
}
