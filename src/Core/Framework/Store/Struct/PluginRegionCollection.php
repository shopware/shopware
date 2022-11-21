<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @package merchant-services
 *
 * @codeCoverageIgnore
 * Pseudo immutable collection
 *
 * @extends Collection<PluginRegionStruct>
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
