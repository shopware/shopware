<?php

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class CustomFields extends Field
{
    public const TYPE = 'custom-fields';

    public function __construct(public bool $translated = false)
    {
        parent::__construct(type: self::TYPE, translated: $this->translated, api: true);
    }
}
