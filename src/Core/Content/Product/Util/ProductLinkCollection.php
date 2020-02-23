<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Util;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void             add(ProductLink $entity)
 * @method void             set(string $key, ProductLink $entity)
 * @method ProductLink[]    getIterator()
 * @method ProductLink[]    getElements()
 * @method ProductLink|null get(string $key)
 * @method ProductLink|null first()
 * @method ProductLink|null last()
 */
class ProductLinkCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return ProductLink::class;
    }
}
