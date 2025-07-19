<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\QuickView;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('framework')]
class MinimalQuickViewPage extends Struct
{
    /**
     * @internal
     */
    public function __construct(
        protected ProductEntity $product,
    ) {
    }

    public function getProduct(): ProductEntity
    {
        return $this->product;
    }
}
