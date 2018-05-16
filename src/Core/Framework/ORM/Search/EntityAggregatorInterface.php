<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search;

use Shopware\Application\Context\Struct\ApplicationContext;

interface EntityAggregatorInterface
{
    public function aggregate(string $definition, Criteria $criteria, ApplicationContext $context): AggregatorResult;
}
