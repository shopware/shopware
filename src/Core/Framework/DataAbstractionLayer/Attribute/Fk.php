<?php

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Fk extends Field
{
    public const TYPE = 'fk';

    public bool $nullable;

    public function __construct(public string $entity, public bool|array $api = false)
    {
        parent::__construct(type: self::TYPE, api: $api);
    }
}
