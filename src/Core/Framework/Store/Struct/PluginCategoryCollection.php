<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @codeCoverageIgnore
 * Pseudo immutable collection
 *
 * @extends Collection<PluginCategoryStruct>
 */
#[Package('merchant-services')]
final class PluginCategoryCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'store_category_collection';
    }

    protected function getExpectedClass(): string
    {
        return PluginCategoryStruct::class;
    }
}
