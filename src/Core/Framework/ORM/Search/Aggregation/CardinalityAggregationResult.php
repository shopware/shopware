<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search\Aggregation;

class CardinalityAggregationResult extends AggregationResult
{
    /**
     * @var iterable
     */
    protected $cardinality;

    public function __construct(Aggregation $aggregation, iterable $cardinality)
    {
        parent::__construct($aggregation);

        $this->cardinality = $cardinality;
    }

    public function getCardinality(): iterable
    {
        return $this->cardinality;
    }

    public function getResult(): array
    {
        return $this->cardinality;
    }
}
