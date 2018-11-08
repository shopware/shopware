<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation;

class ValueAggregationResult extends AggregationResult
{
    /**
     * @var iterable
     */
    protected $values;

    public function __construct(Aggregation $aggregation, iterable $values)
    {
        parent::__construct($aggregation);

        $this->values = $values;
    }

    public function getValues(): iterable
    {
        return $this->values;
    }

    public function getResult(): array
    {
        return $this->values;
    }
}
