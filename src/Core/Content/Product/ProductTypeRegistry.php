<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductTypeRegistry
{
    /**
     * @param array<string> $types
     */
    public function __construct(public array $types)
    {
    }

    public function addType(string $type): void
    {
        if ($this->hasType($type)) {
            return;
        }

        $this->types[] = $type;
    }

    /**
     * @return array<int, string>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function hasType(string $type): bool
    {
        return \in_array($type, $this->types, true);
    }
}
