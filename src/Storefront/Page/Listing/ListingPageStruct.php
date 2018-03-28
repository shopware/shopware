<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Listing\Collection\ListingSortingBasicCollection;
use Shopware\Api\Product\Struct\ProductSearchResult;
use Shopware\Framework\Struct\Struct;
use Shopware\Storefront\Page\Listing\AggregationView\AggregationViewCollection;

class ListingPageStruct extends Struct
{
    /**
     * @var ProductSearchResult
     */
    protected $products;

    /**
     * @var Criteria
     */
    protected $criteria;

    /**
     * @var bool
     */
    protected $showListing = true;

    /**
     * @var int
     */
    protected $currentPage;

    /**
     * @var int
     */
    protected $pageCount;

    /**
     * @var string|null
     */
    protected $currentSorting;

    /**
     * @var AggregationViewCollection
     */
    protected $aggregations;

    /**
     * @var ListingSortingBasicCollection
     */
    protected $sortings;

    /**
     * @var string
     */
    protected $productBoxLayout;

    public function __construct(
        ProductSearchResult $products,
        Criteria $criteria,
        int $currentPage = 1,
        int $pageCount = 1,
        bool $showListing = true,
        ?string $currentSorting = null,
        string $productBoxLayout = 'basic',
        ?AggregationViewCollection $aggregations = null,
        ?ListingSortingBasicCollection $sortings = null
    ) {
        $this->products = $products;
        $this->criteria = $criteria;
        $this->showListing = $showListing;
        $this->currentPage = $currentPage;
        $this->pageCount = $pageCount;
        $this->currentSorting = $currentSorting;
        $this->productBoxLayout = $productBoxLayout;

        $aggregations = $aggregations ?? new AggregationViewCollection();
        $sortings = $sortings ?? new ListingSortingBasicCollection();

        $this->aggregations = $aggregations;
        $this->sortings = $sortings;
    }

    public function getProducts(): ProductSearchResult
    {
        return $this->products;
    }

    public function setProducts(ProductSearchResult $products): void
    {
        $this->products = $products;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function setCriteria(Criteria $criteria): void
    {
        $this->criteria = $criteria;
    }

    public function showListing(): bool
    {
        return $this->showListing;
    }

    public function setShowListing(bool $showListing): void
    {
        $this->showListing = $showListing;
    }

    public function setCurrentPage(int $page): void
    {
        $this->currentPage = $page;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function setPageCount(int $count): void
    {
        $this->pageCount = $count;
    }

    public function getPageCount(): int
    {
        return $this->pageCount;
    }

    public function setAggregations(AggregationViewCollection $aggregations): void
    {
        $this->aggregations = $aggregations;
    }

    public function getAggregations(): AggregationViewCollection
    {
        return $this->aggregations;
    }

    public function getSortings(): ListingSortingBasicCollection
    {
        return $this->sortings;
    }

    public function getCurrentSorting(): ?string
    {
        return $this->currentSorting;
    }

    public function setCurrentSorting(?string $currentSorting): void
    {
        $this->currentSorting = $currentSorting;
    }

    public function getProductBoxLayout(): string
    {
        return $this->productBoxLayout;
    }

    public function setProductBoxLayout(string $productBoxLayout): void
    {
        $this->productBoxLayout = $productBoxLayout;
    }
}
