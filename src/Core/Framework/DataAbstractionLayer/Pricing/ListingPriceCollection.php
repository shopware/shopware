<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Pricing;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Collection;

class ListingPriceCollection extends Collection
{
    public function getContextPrice(Context $context): ?ListingPrice
    {
        foreach ($context->getRuleIds() as $ruleId) {
            $prices = $this->filterByRuleId($this->elements, $ruleId);

            if (\count($prices) > 0) {
                $prices = $this->filterByCurrencyId($prices, $context->getCurrencyId());

                return array_shift($prices);
            }
        }

        return null;
    }

    public function getApiAlias(): string
    {
        return 'listing_price_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return ListingPrice::class;
    }

    private function filterByCurrencyId(iterable $prices, string $currencyId): array
    {
        $filtered = [];
        /** @var ListingPrice $price */
        foreach ($prices as $price) {
            if ($price->getCurrencyId() === $currencyId) {
                $filtered[] = $price;
            }
        }

        return $filtered;
    }

    private function filterByRuleId(iterable $prices, string $ruleId): array
    {
        $filtered = [];
        /** @var ListingPrice $price */
        foreach ($prices as $price) {
            if ($price->getRuleId() === $ruleId) {
                $filtered[] = $price;
            }
        }

        return $filtered;
    }
}
