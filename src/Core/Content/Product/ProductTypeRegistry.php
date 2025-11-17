<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\ProductType\AbstractProductType;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductTypeRegistry
{
    /**
     * @var array<string, AbstractProductType>
     */
    private array $types = [];

    /**
     * @param iterable<AbstractProductType> $typeServices
     */
    public function __construct(iterable $typeServices = [])
    {
        foreach ($typeServices as $service) {
            $this->addType($service);
        }
    }

    public function addType(AbstractProductType $type): void
    {
        $this->types[$type->getType()] = $type;
    }

    /**
     * @return array<int, string>
     */
    public function getTypes(): array
    {
        return array_keys($this->types);
    }

    /**
     * @return array<int, AbstractProductType>
     */
    public function getTypeHandlers(): array
    {
        return array_values($this->types);
    }

    public function getTypeHandler(string $type): ?AbstractProductType
    {
        return $this->types[$type] ?? null;
    }
}
