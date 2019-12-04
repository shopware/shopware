<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\CrossSelling;

use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Struct\Struct;

class CrossSellingElement extends Struct
{
    /**
     * @var ProductCrossSellingEntity
     */
    protected $crossSelling;

    /**
     * @var ProductCollection
     */
    protected $products;

    /**
     * @var int
     */
    protected $total;

    public function getCrossSelling(): ProductCrossSellingEntity
    {
        return $this->crossSelling;
    }

    public function setCrossSelling(ProductCrossSellingEntity $crossSelling): void
    {
        $this->crossSelling = $crossSelling;
    }

    public function getProducts(): ProductCollection
    {
        return $this->products;
    }

    public function setProducts(ProductCollection $products): void
    {
        $this->products = $products;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }
}
