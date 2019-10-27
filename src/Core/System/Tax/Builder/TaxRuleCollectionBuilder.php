<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Builder;

use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\TaxAreaRuleType\NotMatchingTaxAreaRule;
use Shopware\Core\System\Tax\TaxAreaRuleType\TaxAreaRuleTypeFilterInterface;
use Shopware\Core\System\Tax\TaxEntity;

class TaxRuleCollectionBuilder implements TaxRuleCollectionBuilderInterface
{
    /**
     * @var TaxAreaRuleTypeFilterInterface[]|iterable
     */
    private $taxAreaRuleTypeFilter;

    public function __construct(iterable $taxAreaRuleTypeFilter)
    {
        $this->taxAreaRuleTypeFilter = $taxAreaRuleTypeFilter;
    }

    public function buildTaxRuleCollection(TaxEntity $taxEntity, SalesChannelContext $salesChannelContext): TaxRuleCollection
    {
        $taxRate = $this->getTaxRate(
            $salesChannelContext->getTaxRules()->get($taxEntity->getId()),
            $salesChannelContext
        );

        return new TaxRuleCollection([
            new TaxRule($taxRate, 100),
        ]);
    }

    private function getTaxRate(TaxEntity $taxEntity, SalesChannelContext $salesChannelContext): float
    {
        if ($taxEntity->getTaxAreaRules() === null) {
            return $taxEntity->getTaxRate();
        }

        foreach ($this->taxAreaRuleTypeFilter as $ruleTypeFilter) {
            foreach ($taxEntity->getTaxAreaRules() as $taxAreaRule) {
                try {
                    return $ruleTypeFilter->getTaxRate($taxAreaRule, $salesChannelContext);
                } catch (NotMatchingTaxAreaRule $notSupportedException) {
                    // nth
                }
            }
        }

        return $taxEntity->getTaxRate();
    }
}
