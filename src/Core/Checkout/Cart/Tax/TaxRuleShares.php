<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
final class TaxRuleShares
{
    /**
     * Converts rule percentages that are shares of the net price into the matching shares of the gross price
     */
    public static function ofGross(TaxRuleCollection $netShares): TaxRuleCollection
    {
        return self::reweigh(
            $netShares,
            static fn (TaxRule $rule): float => $rule->getPercentage() * (100 + $rule->getTaxRate()) / 100
        );
    }

    /**
     * Converts rule percentages that are shares of the gross price into the matching shares of the net price
     */
    public static function ofNet(TaxRuleCollection $grossShares): TaxRuleCollection
    {
        return self::reweigh(
            $grossShares,
            static fn (TaxRule $rule): float => $rule->getPercentage() * 100 / (100 + $rule->getTaxRate())
        );
    }

    /**
     * @param \Closure(TaxRule): float $weigh
     */
    private static function reweigh(TaxRuleCollection $rules, \Closure $weigh): TaxRuleCollection
    {
        $untaxed = 100.0;
        $weights = [];
        foreach ($rules as $key => $rule) {
            $weights[$key] = $weigh($rule);
            $untaxed -= $rule->getPercentage();
        }

        $total = array_sum($weights) + $untaxed;
        if ($total === 0.0) {
            return $rules;
        }

        $reweighed = new TaxRuleCollection();
        foreach ($rules as $key => $rule) {
            $reweighed->add(new TaxRule($rule->getTaxRate(), $weights[$key] / $total * 100));
        }

        return $reweighed;
    }
}
