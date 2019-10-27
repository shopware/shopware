<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\TaxAreaRuleType;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleEntity;

interface TaxAreaRuleTypeFilterInterface
{
    public function getTaxRate(TaxAreaRuleEntity $taxAreaRuleEntity, SalesChannelContext $salesChannelContext): float;
}
