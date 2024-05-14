<?php

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Serialized extends Field
{
    public const TYPE = 'serialized';

    public function __construct(
        public string $serializer = StringFieldSerializer::class,
        public bool|array $api = false
    ) {
        parent::__construct(type: self::TYPE, api: $api);
    }
}
