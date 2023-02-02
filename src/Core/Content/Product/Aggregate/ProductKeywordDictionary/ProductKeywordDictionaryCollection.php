<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductKeywordDictionary;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductKeywordDictionaryEntity>
 */
#[Package('inventory')]
class ProductKeywordDictionaryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_keyword_dictionary_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductKeywordDictionaryEntity::class;
    }
}
