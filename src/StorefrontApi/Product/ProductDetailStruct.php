<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Product;

use Shopware\Api\Product\Collection\ProductServiceBasicCollection;
use Shopware\StorefrontApi\Product\ProductBasicStruct;

class ProductDetailStruct extends ProductBasicStruct
{
    /**
     * @var ProductServiceBasicCollection
     */
    protected $services;

    public function __construct()
    {
        parent::__construct();
        $this->services = new ProductServiceBasicCollection();
    }

    public function getServices(): ProductServiceBasicCollection
    {
        return $this->services;
    }

    public function setServices(ProductServiceBasicCollection $services): void
    {
        $this->services = $services;
    }
}
