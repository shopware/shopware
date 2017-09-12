<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Category\Struct\CategoryBasicStruct;
use Shopware\Framework\Struct\Struct;
use Shopware\Product\Struct\ProductBasicCollection;

class ListingPageStruct extends Struct
{
    /**
     * @var CategoryBasicStruct
     */
    protected $category;

    /**
     * @var ProductBasicCollection
     */
    protected $products;

    public function getCategory(): CategoryBasicStruct
    {
        return $this->category;
    }

    public function setCategory(CategoryBasicStruct $category): void
    {
        $this->category = $category;
    }

    public function getProducts(): ProductBasicCollection
    {
        return $this->products;
    }

    public function setProducts(ProductBasicCollection $products): void
    {
        $this->products = $products;
    }
}
