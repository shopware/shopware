<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\ORM\EntityCollection;

class EntitySearchResult
{
    /**
     * @var int
     */
    protected $total;

    /**
     * @var EntityCollection
     */
    protected $entities;

    /**
     * @var AggregatorResult
     */
    protected $aggregations;

    /**
     * @var Criteria
     */
    protected $criteria;

    /**
     * @var ApplicationContext
     */
    protected $context;

    public function __construct(
        int $total,
        EntityCollection $entities,
        AggregatorResult $aggregations,
        Criteria $criteria,
        ApplicationContext $context
    ) {
        $this->total = $total;
        $this->entities = $entities;
        $this->aggregations = $aggregations;
        $this->criteria = $criteria;
        $this->context = $context;
    }
}
