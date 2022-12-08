<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Hreflang;

use Shopware\Core\Framework\Struct\StructCollection;

/**
 * @package sales-channel
 *
 * @extends StructCollection<HreflangStruct>
 */
class HreflangCollection extends StructCollection
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
