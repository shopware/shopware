<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * Pseudo immutable collection
 *
 * @method PluginRegionStruct[]    getIterator()
 * @method PluginRegionStruct[]    getElements()
 * @method PluginRegionStruct|null get(string $key)
 * @method PluginRegionStruct|null first()
 * @method PluginRegionStruct|null last()
 */
final class PluginRegionCollection extends Collection
{
    public function getExpectedClass(): string
    {
        return PluginRegionStruct::class;
    }

    public function getApiAlias(): string
    {
        return 'store_plugin_region_collection';
    }
}
