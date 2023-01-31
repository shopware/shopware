<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\QuickView;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('storefront')]
class MinimalQuickViewPage extends Struct
{
    /**
     * @var ProductEntity
     */
    protected $product;

    /**
     * @internal
     */
    public function __construct(ProductEntity $product)
    {
        $this->product = $product;
    }

    public function getProduct(): ProductEntity
    {
        return $this->product;
    }
}
