<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductKeywordDictionary;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                add(ProductKeywordDictionaryEntity $entity)
 * @method void                                set(string $key, ProductKeywordDictionaryEntity $entity)
 * @method ProductKeywordDictionaryEntity[]    getIterator()
 * @method ProductKeywordDictionaryEntity[]    getElements()
 * @method ProductKeywordDictionaryEntity|null get(string $key)
 * @method ProductKeywordDictionaryEntity|null first()
 * @method ProductKeywordDictionaryEntity|null last()
 */
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
