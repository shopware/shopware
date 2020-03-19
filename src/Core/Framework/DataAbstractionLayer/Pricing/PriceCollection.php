<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Pricing;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void       set(string $key, Price $entity)
 * @method Price[]    getIterator()
 * @method Price[]    getElements()
 * @method Price|null get(string $currencyId)
 * @method Price|null first()
 * @method Price|null last()
 */
class PriceCollection extends Collection
{
    public function add($element): void
    {
        $this->validateType($element);

        /* @var Price $element */
        $this->elements[$element->getCurrencyId()] = $element;
    }

    public function getCurrencyPrice(string $currencyId, bool $fallback = true): ?Price
    {
        $price = $this->get($currencyId);

        if ($price) {
            return $price;
        }

        if ($currencyId === Defaults::CURRENCY) {
            return null;
        }

        if (!$fallback) {
            return null;
        }

        return $this->get(Defaults::CURRENCY);
    }

    public function getApiAlias(): string
    {
        return 'price_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return Price::class;
    }
}
