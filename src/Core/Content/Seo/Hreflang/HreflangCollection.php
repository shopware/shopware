<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Hreflang;

use Shopware\Core\Framework\Struct\StructCollection;

/**
 * @method void                add(HreflangStruct $entity)
 * @method void                set(string $key, HreflangStruct $entity)
 * @method HreflangStruct[]    getIterator()
 * @method HreflangStruct[]    getElements()
 * @method HreflangStruct|null get(string $key)
 * @method HreflangStruct|null first()
 * @method HreflangStruct|null last()
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
