<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Core\Framework\Struct\Struct;

class ListingPageRequest extends Struct
{
    /**
     * @var int
     */
    protected $page;

    /**
     * @var int
     */
    protected $limit;

    /**
     * @var string|null
     */
    protected $sortingKey;

    /**
     * @var string|null
     */
    protected $navigationId;

    /**
     * @var string[]
     */
    protected $manufacturerNames = [];

    /**
     * @var string[]
     */
    protected $datasheetIds = [];

    /**
     * @var float|null
     */
    protected $minPrice;

    /**
     * @var float|null
     */
    protected $maxPrice;

    /**
     * @var bool
     */
    protected $loadAggregations = true;

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

    public function getSortingKey(): ?string
    {
        return $this->sortingKey;
    }

    public function setSortingKey(?string $sortingKey): void
    {
        $this->sortingKey = $sortingKey;
    }

    public function getNavigationId(): string
    {
        return $this->navigationId;
    }

    public function setNavigationId(string $navigationId): void
    {
        $this->navigationId = $navigationId;
    }

    public function getManufacturerNames(): array
    {
        return $this->manufacturerNames;
    }

    public function setManufacturerNames(array $manufacturerNames): void
    {
        $this->manufacturerNames = $manufacturerNames;
    }

    public function getDatasheetIds(): array
    {
        return $this->datasheetIds;
    }

    public function setDatasheetIds(array $datasheetIds): void
    {
        $this->datasheetIds = $datasheetIds;
    }

    public function loadAggregations(): bool
    {
        return $this->loadAggregations;
    }

    public function setLoadAggregations(bool $loadAggregations): void
    {
        $this->loadAggregations = $loadAggregations;
    }

    public function getMinPrice(): ?float
    {
        return $this->minPrice;
    }

    public function setMinPrice(?float $minPrice): void
    {
        $this->minPrice = $minPrice;
    }

    public function getMaxPrice(): ?float
    {
        return $this->maxPrice;
    }

    public function setMaxPrice(?float $maxPrice): void
    {
        $this->maxPrice = $maxPrice;
    }
}
