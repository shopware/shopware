<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @package merchant-services
 *
 * @codeCoverageIgnore
 * Pseudo immutable collection
 *
 * @extends Collection<PluginCategoryStruct>
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
