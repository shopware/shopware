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
     * @var int
     */
    protected $page;

    /**
     * @var int
     */
    protected $limit;

    /**
     * @var ProductListingSorting[]
     *
     * @deprecated tag:v6.4.0 - use availableSortings instead
     */
    protected $sortings = [];

    /**
     * @var ProductSortingCollection
     */
    protected $availableSortings;

    public function addCurrentFilter(string $key, $value): void
    {
        $this->currentFilters[$key] = $value;
    }

    /**
     * @deprecated tag:v6.4.0 - use getAvailableSortings() instead
     */
    public function getSortings(): array
    {
        return $this->sortings;
    }

    /**
     * @deprecated tag:v6.4.0 - use setAvailableSortings() instead
     */
    public function setSortings(array $sortings): void
    {
        $this->sortings = $sortings;
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

    public function getPage(): int
    {
        return $this->page;
    }

    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
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
