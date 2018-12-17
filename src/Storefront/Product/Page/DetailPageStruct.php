<?php declare(strict_types=1);

namespace Shopware\Storefront\Product\Page;

use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Struct\Struct;

class DetailPageStruct extends Struct
{
    /**
     * @var ProductEntity
     */
    protected $product;

    /**
     * @var ProductConfiguratorCollection
     */
    protected $configurator;

    public function __construct(ProductEntity $product)
    {
        $this->product = $product;
        $this->configurator = new ProductConfiguratorCollection();
    }

    public function getConfigurator(): ProductConfiguratorCollection
    {
        return $this->configurator;
    }

    public function setConfigurator(ProductConfiguratorCollection $configurator): void
    {
        $this->configurator = $configurator;
    }

    public function getProduct(): ProductEntity
    {
        return $this->product;
    }

    public function setProduct(ProductEntity $product): void
    {
        $this->product = $product;
    }
}
