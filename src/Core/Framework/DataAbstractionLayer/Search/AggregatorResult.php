<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @final
 */
class AggregatorResult extends Struct
{
    public function __construct(
        private  AggregationResultCollection $aggregations,
        private  Context $context,
        private Criteria $criteria
    ) {
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

    public function getApiAlias(): string
    {
        return 'dal_aggregator_result';
    }
}
