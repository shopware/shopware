<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search;

use Shopware\Context\Struct\ApplicationContext;

interface EntityAggregatorInterface
{
    public function aggregate(string $definition, Criteria $criteria, ApplicationContext $context): AggregatorResult;
}
