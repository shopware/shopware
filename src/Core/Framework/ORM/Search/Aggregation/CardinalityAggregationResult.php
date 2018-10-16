<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search\Aggregation;

class CardinalityAggregationResult extends AggregationResult
{
    /**
     * @var int
     */
    protected $cardinality;

    public function __construct(Aggregation $aggregation, int $cardinality)
    {
        parent::__construct($aggregation);

        $this->cardinality = $cardinality;
    }

    public function getCardinality(): int
    {
        return $this->cardinality;
    }

    public function getResult(): array
    {
        return ['cardinality' => $this->cardinality];
    }
}
