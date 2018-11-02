<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation;

class MaxAggregation implements Aggregation
{
    use AggregationTrait;

    public function __construct(string $field, string $name)
    {
        $this->field = $field;
        $this->name = $name;
    }
}
