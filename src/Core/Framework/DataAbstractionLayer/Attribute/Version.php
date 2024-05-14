<?php

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Version extends Field
{
    public const TYPE = 'version';

    public function __construct()
    {
        parent::__construct(type: self::TYPE, api: true);
    }
}
