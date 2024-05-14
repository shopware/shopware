<?php

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ManyToMany extends Field
{
    public const TYPE = 'many-to-many';

    public function __construct(
        public string $entity,
        public OnDelete $onDelete = OnDelete::NO_ACTION,
        public bool|array $api = false
    ) {
        parent::__construct(type: self::TYPE, api: $api);
    }
}
