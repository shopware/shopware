<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Detail;

use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorCollection;
use Shopware\Core\Content\Product\ProductStruct;
use Shopware\Core\Framework\Struct\Struct;

class DetailPageStruct extends Struct
{
    /**
     * @var ProductStruct
     */
    protected $product;

    /**
     * @var \Shopware\Core\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorCollection
     */
    protected $configurator;

    public function __construct(ProductStruct $product)
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

    public function getProduct(): ProductStruct
    {
        return $this->product;
    }

    public function setProduct(ProductStruct $product): void
    {
        $this->product = $product;
    }
}
