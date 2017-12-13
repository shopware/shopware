<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search;

use Shopware\Context\Struct\TranslationContext;

interface EntityAggregatorInterface
{
    public function aggregate(string $definition, Criteria $criteria, TranslationContext $context): AggregationResult;
}
