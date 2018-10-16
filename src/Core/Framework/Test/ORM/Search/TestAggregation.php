<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Search;

use Shopware\Core\Framework\ORM\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\ORM\Search\Aggregation\AggregationTrait;

class TestAggregation implements Aggregation
{
    use AggregationTrait;

    public function __construct(string $field, string $name)
    {
        $this->field = $field;
        $this->name = $name;
    }
}
