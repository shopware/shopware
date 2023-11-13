<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
interface EntityAggregatorInterface
{
    public function aggregate(EntityDefinition $definition, Criteria $criteria, Context $context): AggregationResultCollection;
}
