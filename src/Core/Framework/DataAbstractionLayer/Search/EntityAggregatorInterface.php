<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Context;

interface EntityAggregatorInterface
{
    public function aggregate(string $definition, Criteria $criteria, Context $context): AggregatorResult;
}
