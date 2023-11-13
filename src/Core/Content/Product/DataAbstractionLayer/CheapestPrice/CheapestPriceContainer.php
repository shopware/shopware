<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('core')]
class CheapestPriceContainer extends Struct
{
    /**
     * @var array<mixed>
     */
    protected array $value;

    /**
     * @var array<mixed>|null
     */
    protected ?array $default = null;

    /**
     * @var list<string>|null
     */
    private ?array $ruleIds = null;

    /**
     * @param array<mixed> $value
     */
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

        // NEXT-21735 - This is covered randomly
        // @codeCoverageIgnoreStart
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
        // @codeCoverageIgnoreEnd

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

            if (isset($row['regulationPrice'])) {
                $regulation = clone $blueprint;

                $regulation->setCurrencyId($row['currencyId']);
                $regulation->setGross((float) $row['regulationPrice']['gross']);
                $regulation->setNet((float) $row['regulationPrice']['net']);
                $regulation->setLinked((bool) $row['regulationPrice']['linked']);

                $price->setRegulationPrice($regulation);
            }

            if (isset($row['percentage'])) {
                $price->setPercentage([
                    'gross' => $row['percentage']['gross'],
                    'net' => $row['percentage']['net'],
                ]);
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

    /**
     * @return array<mixed>
     */
    public function getValue(): array
    {
        return $this->value;
    }

    /**
     * @return array<mixed>
     */
    public function getPricesForVariant(string $variantId): array
    {
        return $this->value[$variantId] ?? [];
    }

    /**
     * @return array<string>
     */
    public function getVariantIds(): array
    {
        return \array_keys($this->value);
    }

    /**
     * @return array<mixed>
     */
    public function getDefault(): ?array
    {
        return $this->default;
    }

    /**
     * @return list<string>
     */
    public function getRuleIds(): array
    {
        if ($this->ruleIds === null) {
            $ruleIds = [];

            foreach ($this->value as $group) {
                foreach ($group as $price) {
                    /** @var string|null $ruleId */
                    $ruleId = $price['rule_id'] ?? null;
                    if ($ruleId === null) {
                        continue;
                    }

                    $ruleIds[$price['rule_id']] = true;
                }
            }

            /** @var list<string> $ruleIds */
            $ruleIds = array_keys($ruleIds);
            $this->ruleIds = $ruleIds;
        }

        return $this->ruleIds;
    }

    /**
     * @param array<mixed> $prices
     *
     * @return array<mixed>|null
     */
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

    /**
     * @param array<mixed> $price
     */
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

    /**
     * @param array<mixed> $collection
     *
     * @return array<mixed>|null
     */
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
