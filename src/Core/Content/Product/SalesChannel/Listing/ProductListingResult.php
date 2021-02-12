<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class ProductListingResult extends EntitySearchResult
{
    /**
     * @var string|null
     */
    protected $sorting;

    /**
     * @var array
     */
    protected $currentFilters = [];

    /**
     * @var ProductSortingCollection
     */
    protected $availableSortings;

    public function addCurrentFilter(string $key, $value): void
    {
        $this->currentFilters[$key] = $value;
    }

    public function getAvailableSortings(): ProductSortingCollection
    {
        return $this->availableSortings;
    }

    public function setAvailableSortings(ProductSortingCollection $availableSortings): void
    {
        $this->availableSortings = $availableSortings;
    }

    public function getSorting(): ?string
    {
        return $this->sorting;
    }

    public function setSorting(?string $sorting): void
    {
        $this->sorting = $sorting;
    }

    public function getCurrentFilters(): array
    {
        return $this->currentFilters;
    }

    public function getCurrentFilter(string $key)
    {
        return $this->currentFilters[$key] ?? null;
    }

    public function getApiAlias(): string
    {
        return 'product_listing';
    }
}
