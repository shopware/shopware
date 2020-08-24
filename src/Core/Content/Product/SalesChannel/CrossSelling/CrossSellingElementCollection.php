<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\CrossSelling;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void                     add(CrossSellingElement $entity)
 * @method void                     set(string $key, CrossSellingElement $entity)
 * @method CrossSellingElement[]    getIterator()
 * @method CrossSellingElement[]    getElements()
 * @method CrossSellingElement|null get(string $key)
 * @method CrossSellingElement|null first()
 * @method CrossSellingElement|null last()
 */
class CrossSellingElementCollection extends Collection
{
    public function getExpectedClass(): ?string
    {
        return CrossSellingElement::class;
    }

    public function getApiAlias(): string
    {
        return 'cross_selling_elements';
    }
}
