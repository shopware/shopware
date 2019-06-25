<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Pricing;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Collection;

class ListingPriceCollection extends Collection
{
    public function filterByCurrencyId(string $currencyId)
    {
        return $this->filter(function (ListingPrice $price) use ($currencyId) {
            return $price->getCurrencyId() === $currencyId;
        });
    }

    public function filterByRuleId(string $ruleId)
    {
        return $this->filter(function (ListingPrice $price) use ($ruleId) {
            return $price->getRuleId() === $ruleId;
        });
    }

    public function getContextPrice(Context $context): ?ListingPrice
    {
        foreach ($context->getRuleIds() as $ruleId) {
            $prices = $this->filterByRuleId($ruleId);

            if ($prices->count() > 0) {
                return $prices
                    ->filterByCurrencyId($context->getCurrencyId())
                    ->first();
            }
        }

        return null;
    }

    protected function getExpectedClass(): ?string
    {
        return ListingPrice::class;
    }
}
