<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Content\Product\Storefront\StorefrontProductEntity;
use Shopware\Storefront\Framework\Page\PageWithHeader;

class ProductPage extends PageWithHeader
{
    /**
     * @var StorefrontProductEntity
     */
    protected $product;

    public function getProduct(): StorefrontProductEntity
    {
        return $this->product;
    }

    public function setProduct(StorefrontProductEntity $product): void
    {
        $this->product = $product;
    }
}
