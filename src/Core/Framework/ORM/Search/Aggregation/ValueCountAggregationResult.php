<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search\Aggregation;

class ValueCountAggregationResult extends AggregationResult
{
    /**
     * @var array
     */
    protected $values;

    public function __construct(Aggregation $aggregation, array $values)
    {
        parent::__construct($aggregation);
        $this->values = $values;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function getResult(): array
    {
        return $this->values;
    }
}
