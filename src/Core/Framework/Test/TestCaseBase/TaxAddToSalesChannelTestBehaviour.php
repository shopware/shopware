<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleCollection;
use Shopware\Core\System\Tax\TaxEntity;

trait TaxAddToSalesChannelTestBehaviour
{
    protected function addTaxDataToSalesChannel(SalesChannelContext $salesChannelContext, array $taxData): void
    {
        $tax = (new TaxEntity())->assign($taxData);
        $tax->setTaxAreaRules(new TaxAreaRuleCollection());

        $salesChannelContext->getTaxRules()->add($tax);
    }

    protected function addTaxEntityToSalesChannel(SalesChannelContext $salesChannelContext, TaxEntity $taxEntity): void
    {
        if ($taxEntity->getTaxAreaRules() === null) {
            $taxEntity->setTaxAreaRules(new TaxAreaRuleCollection());
        }

        $salesChannelContext->getTaxRules()->add($taxEntity);
    }
}
