<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Pricing;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Collection;

class ListingPriceCollection extends Collection
{
    public function getContextPrice(Context $context): ?ListingPrice
    {
        $ruleIds = $context->getRuleIds();
        $ruleIds[] = null;

        foreach ($ruleIds as $ruleId) {
            $price = $this->getRulePrice($ruleId, $context);

            if ($price) {
                return $price;
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

    private function getRulePrice(?string $ruleId, Context $context): ?ListingPrice
    {
        $prices = $this->filterByRuleId($this->elements, $ruleId);

        $price = $this->getCurrencyPrice($prices, $context->getCurrencyId());

        if (!$price) {
            $price = $this->getCurrencyPrice($prices, Defaults::CURRENCY);
        }

        return $price;
    }

    private function getCurrencyPrice(array $prices, string $currencyId): ?ListingPrice
    {
        $prices = $this->filterByCurrencyId($prices, $currencyId);

        return array_shift($prices);
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

    private function filterByRuleId(iterable $prices, ?string $ruleId): array
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
