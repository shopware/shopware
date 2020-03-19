<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                            add(ProductSearchKeywordEntity $entity)
 * @method void                            set(string $key, ProductSearchKeywordEntity $entity)
 * @method ProductSearchKeywordEntity[]    getIterator()
 * @method ProductSearchKeywordEntity[]    getElements()
 * @method ProductSearchKeywordEntity|null get(string $key)
 * @method ProductSearchKeywordEntity|null first()
 * @method ProductSearchKeywordEntity|null last()
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
