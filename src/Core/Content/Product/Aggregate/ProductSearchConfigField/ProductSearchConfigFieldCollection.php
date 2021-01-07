<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal (flag:FEATURE_NEXT_10552)
 *
 * @method void                                add(ProductSearchConfigFieldEntity $entity)
 * @method void                                set(string $key, ProductSearchConfigFieldEntity $entity)
 * @method ProductSearchConfigFieldEntity[]    getIterator()
 * @method ProductSearchConfigFieldEntity[]    getElements()
 * @method ProductSearchConfigFieldEntity|null get(string $key)
 * @method ProductSearchConfigFieldEntity|null first()
 * @method ProductSearchConfigFieldEntity|null last()
 */
class ProductSearchConfigFieldCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_search_config_field_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductSearchConfigFieldEntity::class;
    }
}
