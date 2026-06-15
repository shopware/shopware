<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductContentLayout;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 *
 * @extends EntityCollection<ProductContentLayoutEntity>
 */
#[Package('inventory')]
class ProductContentLayoutCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductContentLayoutEntity::class;
    }
}
