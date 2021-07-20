<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Struct\Struct;

class CheapestPriceContainer extends Struct
{
    /**
     * @var array[]
     */
    protected array $value;

    protected ?array $default = null;

    public function __construct(array $value)
    {
        if (isset($value['default'])) {
            $this->default = $value['default'];
            unset($value['default']);
        }

        $this->value = $value;
    }

    public function resolve(Context $context): ?CheapestPrice
    {
        $ruleIds = $context->getRuleIds();
        $ruleIds[] = 'default';

        $prices = [];
        $defaultWasAdded = false;
        foreach ($this->value as $variantId => $group) {
            foreach ($ruleIds as $ruleId) {
                $price = $this->filterByRuleId($group, $ruleId, $defaultWasAdded);

                if ($price === null) {
                    continue;
                }

                // overwrite the variantId in case the default price was added
                $price['variant_id'] = $variantId;
                $prices[] = $price;

                break;
            }
        }

        if (empty($prices)) {
            return null;
        }

        $cheapest = array_shift($prices);

        $reference = $this->getPriceValue($cheapest, $context);

        $hasRange = (bool) $cheapest['is_ranged'];

        foreach ($prices as $price) {
            $current = $this->getPriceValue($price, $context);

            if ($current === null) {
                continue;
            }

            if ($current !== $reference || $price['is_ranged']) {
                $hasRange = true;
            }

            if ($current < $reference) {
                $reference = $current;
                $cheapest = $price;
            }
        }

        $object = new CheapestPrice();
        $object->setRuleId($cheapest['rule_id']);
        $object->setVariantId($cheapest['variant_id']);
        $object->setParentId($cheapest['parent_id']);
        $object->setHasRange($hasRange);
        $object->setPurchase($cheapest['purchase_unit'] ? (float) $cheapest['purchase_unit'] : null);
        $object->setReference($cheapest['reference_unit'] ? (float) $cheapest['reference_unit'] : null);
        $object->setUnitId($cheapest['unit_id'] ?? null);

        $prices = [];

        $blueprint = new Price('', 1, 1, true);

        foreach ($cheapest['price'] as $row) {
            $price = clone $blueprint;
            $price->setCurrencyId($row['currencyId']);
            $price->setGross((float) $row['gross']);
            $price->setNet((float) $row['net']);
            $price->setLinked((bool) $row['linked']);

            if (isset($row['listPrice'])) {
                $list = clone $blueprint;

                $list->setCurrencyId($row['currencyId']);
                $list->setGross((float) $row['listPrice']['gross']);
                $list->setNet((float) $row['listPrice']['net']);
                $list->setLinked((bool) $row['listPrice']['linked']);

                $price->setListPrice($list);
            }
            $prices[] = $price;
        }

        $object->setPrice(new PriceCollection($prices));

        return $object;
    }

    public function getApiAlias(): string
    {
        return 'cheapest_price_container';
    }

    public function getValue(): array
    {
        return $this->value;
    }

    public function getPricesForVariant(string $variantId): array
    {
        return $this->value[$variantId] ?? [];
    }

    public function getVariantIds(): array
    {
        return \array_keys($this->value);
    }

    public function getDefault(): ?array
    {
        return $this->default;
    }

    public function getRuleIds(): array
    {
        $ruleIds = [];

        foreach ($this->value as $group) {
            foreach ($group as $price) {
                $ruleIds[] = $price['rule_id'] ?? null;
            }
        }

        return array_filter(array_unique($ruleIds));
    }

    private function filterByRuleId(array $prices, string $ruleId, bool &$defaultWasAdded): ?array
    {
        if (\array_key_exists($ruleId, $prices)) {
            // Null Price is the marker that the default price is inherited
            if ($prices[$ruleId] === null && !$defaultWasAdded) {
                // Make sure to add the default price only once, to not bloat up the intermediate prices array
                $defaultWasAdded = true;

                return $this->default;
            }

            return $prices[$ruleId];
        }

        return null;
    }

    private function getPriceValue(array $price, Context $context): ?float
    {
        $currency = $this->getCurrencyPrice($price['price'], $context->getCurrencyId());

        if (!$currency) {
            return null;
        }

        $value = $context->getTaxState() === CartPrice::TAX_STATE_GROSS ? $currency['gross'] : $currency['net'];

        if ($currency['currencyId'] !== $context->getCurrencyId()) {
            $value *= $context->getCurrencyFactor();
        }

        return $value;
    }

    private function getCurrencyPrice(array $collection, string $currencyId, bool $fallback = true): ?array
    {
        foreach ($collection as $price) {
            if ($price['currencyId'] === $currencyId) {
                return $price;
            }
        }

        if (!$fallback) {
            return null;
        }

        return $this->getCurrencyPrice($collection, Defaults::CURRENCY, false);
    }
}
