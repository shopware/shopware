<?php

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Field
{
    public bool $nullable;

    public function __construct(
        public string $type,
        public bool $translated = false,
        public bool|array $api = false
    ) {}
}
