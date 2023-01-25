<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductCrossSellingIdsCriteriaEvent extends ProductCrossSellingCriteriaEvent
{
}
