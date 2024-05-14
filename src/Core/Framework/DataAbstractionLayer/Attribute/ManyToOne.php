<?php

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ManyToOne extends Field
{
    public const TYPE = 'many-to-one';

    public function __construct(
        public string $entity,
        public OnDelete $onDelete = OnDelete::NO_ACTION,
        public string $ref = 'id' ,
        public bool|array $api = false,
    ) {
        parent::__construct(type: self::TYPE,api: $api);

    }
}
