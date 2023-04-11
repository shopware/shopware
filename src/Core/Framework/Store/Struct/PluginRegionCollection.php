<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @codeCoverageIgnore
 * Pseudo immutable collection
 *
 * @extends Collection<PluginRegionStruct>
 */
#[Package('merchant-services')]
final class PluginRegionCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'store_plugin_region_collection';
    }

    protected function getExpectedClass(): string
    {
        return PluginRegionStruct::class;
    }
}
