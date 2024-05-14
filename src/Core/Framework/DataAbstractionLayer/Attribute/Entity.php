<?php

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Entity
{
    /**
     * @var class-string
     */
    public string $class;

    public function __construct(public string $name) {}
}


