<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Entity;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class EntityAggregatorProfiler implements EntityAggregatorInterface
{
    /**
     * @var EntityAggregatorInterface
     */
    private $decorated;

    /**
     * @var Stopwatch
     */
    private $stopwatch;

    public function __construct(EntityAggregatorInterface $decorated, Stopwatch $stopwatch)
    {
        $this->decorated = $decorated;
        $this->stopwatch = $stopwatch;
    }

    public function aggregate(EntityDefinition $definition, Criteria $criteria, Context $context): AggregationResultCollection
    {
        $this->stopwatch->start('aggregate.' . $definition->getEntityName());

        $data = $this->decorated->aggregate($definition, $criteria, $context);

        $this->stopwatch->stop('aggregate.' . $definition->getEntityName());

        return $data;
    }
}
