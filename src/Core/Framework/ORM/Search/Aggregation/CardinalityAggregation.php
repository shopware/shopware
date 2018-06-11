<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search\Aggregation;

class CardinalityAggregation implements Aggregation
{
    use AggregationTrait;

    public function __construct(string $field, string $name)
    {
        $this->field = $field;
        $this->name = $name;
    }
}
