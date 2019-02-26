<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Storefront\Struct;

use Shopware\Core\Content\Product\Storefront\StorefrontProductEntity;
use Shopware\Core\Framework\Struct\Struct;

class ProductBoxStruct extends Struct
{
    /**
     * @var StorefrontProductEntity
     */
    protected $product;

    /**
     * @var string
     */
    protected $productId;

    public function getProduct(): StorefrontProductEntity
    {
        return $this->product;
    }

    public function setProduct(StorefrontProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }
}
