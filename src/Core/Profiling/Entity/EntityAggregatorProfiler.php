<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Entity;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;

/**
 * @deprecated tag:v6.5.0 - reason:remove-decorator - Will be removed, use the static Profiler::trace method to directly trace functions
 */
class EntityAggregatorProfiler implements EntityAggregatorInterface
{
    private EntityAggregatorInterface $decorated;

    /**
     * @internal
     */
    public function __construct(EntityAggregatorInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function aggregate(EntityDefinition $definition, Criteria $criteria, Context $context): AggregationResultCollection
    {
        return $this->decorated->aggregate($definition, $criteria, $context);
    }
}
