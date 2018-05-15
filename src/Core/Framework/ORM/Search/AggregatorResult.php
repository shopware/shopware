<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search;

use Shopware\Framework\ORM\Search\Aggregation\AggregationResultCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Struct\Struct;

class AggregatorResult extends Struct
{
    /**
     * @var AggregationResultCollection
     */
    protected $aggregations;

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var Criteria
     */
    protected $criteria;

    public function __construct(AggregationResultCollection $aggregations, ApplicationContext $context, Criteria $criteria)
    {
        $this->aggregations = $aggregations;
        $this->context = $context;
        $this->criteria = $criteria;
    }

    public function getAggregations(): AggregationResultCollection
    {
        return $this->aggregations;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }
}
