<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Builder;

use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\TaxEntity;

interface TaxRuleCollectionBuilderInterface
{
    public function buildTaxRuleCollection(TaxEntity $taxEntity, SalesChannelContext $salesChannelContext): TaxRuleCollection;
}
