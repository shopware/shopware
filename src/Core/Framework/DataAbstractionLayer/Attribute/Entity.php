<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Entity
{
    /**
     * @var class-string
     */
    public string $class;

    public function __construct(public string $name, public ?string $parent = null)
    {
    }
}
