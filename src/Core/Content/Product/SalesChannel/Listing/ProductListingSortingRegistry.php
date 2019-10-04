<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

class ProductListingSortingRegistry
{
    /**
     * @var ProductListingSorting[]
     */
    protected $sortings = [];

    public function __construct(iterable $sortings)
    {
        foreach ($sortings as $sorting) {
            $this->add($sorting);
        }
    }

    public function add(ProductListingSorting $sorting): void
    {
        $this->sortings[$sorting->getKey()] = $sorting;
    }

    public function get(string $key): ?ProductListingSorting
    {
        return $this->sortings[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->sortings[$key]);
    }

    public function getSortings(): array
    {
        return $this->sortings;
    }
}
