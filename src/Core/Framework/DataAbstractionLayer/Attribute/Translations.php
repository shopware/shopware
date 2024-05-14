<?php

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Translations extends Field
{
    public const TYPE = 'translations';

    public function __construct()
    {
        parent::__construct(type: self::TYPE, api: true);
    }
}
