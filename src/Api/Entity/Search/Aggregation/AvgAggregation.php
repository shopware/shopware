<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search\Aggregation;

class AvgAggregation implements Aggregation
{
    use AggregationTrait;

    public function __construct(string $field, string $name)
    {
        $this->field = $field;
        $this->name = $name;
    }
}
