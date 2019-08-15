<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation;

use Shopware\Core\Framework\Struct\Struct;

class ExistsAggregation extends Struct implements Aggregation
{
    use AggregationTrait;

    public function __construct(string $field, string $name, string ...$groupByFields)
    {
        $this->field = $field;
        $this->name = $name;
        $this->groupByFields = $groupByFields;
    }
}
