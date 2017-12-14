<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Product\Collection\ProductBasicCollection;
use Shopware\Framework\Struct\Struct;

class SearchPageStruct extends Struct
{
    /**
     * @var ProductBasicCollection
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
     * @var
     */
    protected $productBoxLayout;

    public function getProducts(): ProductBasicCollection
    {
        return $this->products;
    }

    public function setProducts(ProductBasicCollection $products): void
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

    /**
     * @return mixed
     */
    public function getProductBoxLayout(): string
    {
        return $this->productBoxLayout;
    }

    /**
     * @param mixed $productBoxLayout
     */
    public function setProductBoxLayout($productBoxLayout): void
    {
        $this->productBoxLayout = $productBoxLayout;
    }
}
