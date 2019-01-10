<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\WriteNotSupportedException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class TreePathFieldSerializer extends LongTextFieldSerializer
{
    public function getFieldClass(): string
    {
        return TreePathField::class;
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        throw new WriteNotSupportedException($field);
    }
}
