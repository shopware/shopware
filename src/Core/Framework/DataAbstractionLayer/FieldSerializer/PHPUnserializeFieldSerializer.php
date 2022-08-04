<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
class PHPUnserializeFieldSerializer extends AbstractFieldSerializer
{
    /**
     * @internal
     */
    public function __construct()
    {
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        throw new \RuntimeException('Serialized fields can only be written by an indexer');
    }

    public function decode(Field $field, $value)
    {
        if ($value === null) {
            return null;
        }

        return unserialize($value);
    }
}
