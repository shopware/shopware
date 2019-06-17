<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\Struct\Struct;

class AggregatorResult extends Struct
{
    /**
     * @var AggregationResultCollection
     */
    protected $aggregations;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var Criteria
     */
    protected $criteria;

    public function __construct(AggregationResultCollection $aggregations, Context $context, Criteria $criteria)
    {
        $this->aggregations = $aggregations;
        $this->context = $context;
        $this->criteria = $criteria;
    }

    public function getAggregations(): AggregationResultCollection
    {
        return $this->aggregations;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }
}
