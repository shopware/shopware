<?php

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Inherited
{
    public function __construct(public bool $reversed = false) {}
}
