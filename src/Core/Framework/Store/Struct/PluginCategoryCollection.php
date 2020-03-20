<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * Pseudo immutable collection
 *
 * @method PluginCategoryStruct[]    getIterator()
 * @method PluginCategoryStruct[]    getElements()
 * @method PluginCategoryStruct|null get(string $key)
 * @method PluginCategoryStruct|null first()
 * @method PluginCategoryStruct|null last()
 */
final class PluginCategoryCollection extends Collection
{
    public function getExpectedClass(): string
    {
        return PluginCategoryStruct::class;
    }

    public function getApiAlias(): string
    {
        return 'store_category_collection';
    }
}
