<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\DataAbstractionLayer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ProfilingEntityAggregator implements EntityAggregatorInterface
{
    public function __construct(
        private readonly EntityAggregatorInterface $decorated,
        private readonly DalCollector $collector,
    ) {
    }

    public function aggregate(EntityDefinition $definition, Criteria $criteria, Context $context): AggregationResultCollection
    {
        return $this->collector->traceRead(
            DalCollector::OPERATION_AGGREGATE,
            $definition,
            $criteria,
            $context,
            fn (): AggregationResultCollection => $this->decorated->aggregate($definition, $criteria, $context),
        );
    }
}
