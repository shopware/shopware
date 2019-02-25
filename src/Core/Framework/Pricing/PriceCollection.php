<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Pricing;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void       add(Price $entity)
 * @method void       set(string $key, Price $entity)
 * @method Price[]    getIterator()
 * @method Price[]    getElements()
 * @method Price|null get(string $key)
 * @method Price|null first()
 * @method Price|null last()
 */
class PriceCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return Price::class;
    }
}
