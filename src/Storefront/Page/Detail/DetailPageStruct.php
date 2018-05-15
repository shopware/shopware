<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Detail;

use Shopware\Content\Product\Aggregate\ProductConfigurator\Collection\ProductConfiguratorBasicCollection;
use Shopware\Content\Product\Struct\ProductBasicStruct;
use Shopware\Framework\Struct\Struct;

class DetailPageStruct extends Struct
{
    /**
     * @var ProductBasicStruct
     */
    protected $product;

    /**
     * @var \Shopware\Content\Product\Aggregate\ProductConfigurator\Collection\ProductConfiguratorBasicCollection
     */
    protected $configurator;

    public function __construct(ProductBasicStruct $product)
    {
        $this->product = $product;
        $this->configurator = new ProductConfiguratorBasicCollection();
    }

    public function getConfigurator(): ProductConfiguratorBasicCollection
    {
        return $this->configurator;
    }

    public function setConfigurator(ProductConfiguratorBasicCollection $configurator): void
    {
        $this->configurator = $configurator;
    }

    public function getProduct(): ProductBasicStruct
    {
        return $this->product;
    }

    public function setProduct(ProductBasicStruct $product): void
    {
        $this->product = $product;
    }
}
