<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ProductSearchKeywordEntity>
 */
class ProductSearchKeywordCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_search_keyword_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductSearchKeywordEntity::class;
    }
}
