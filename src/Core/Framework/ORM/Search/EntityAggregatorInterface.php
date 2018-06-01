<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search;

use Shopware\Framework\Context;

interface EntityAggregatorInterface
{
    public function aggregate(string $definition, Criteria $criteria, Context $context): AggregatorResult;
}
