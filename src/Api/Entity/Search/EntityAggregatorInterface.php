<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search;

use Shopware\Context\Struct\ShopContext;

interface EntityAggregatorInterface
{
    public function aggregate(string $definition, Criteria $criteria, ShopContext $context): AggregatorResult;
}
