<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Hreflang;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<HreflangStruct>
 */
class HreflangCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'seo_hreflang_collection';
    }

    protected function getExpectedClass(): string
    {
        return HreflangStruct::class;
    }
}
