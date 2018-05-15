<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search\Aggregation;

use Shopware\Framework\Struct\Collection;

class AggregationResultCollection extends Collection
{
    /**
     * @var AggregationResult[]
     */
    protected $elements = [];

    public function add(AggregationResult $result)
    {
        $this->elements[$result->getAggregation()->getName()] = $result;
    }

    public function get(string $name): ?AggregationResult
    {
        if (!$this->has($name)) {
            return null;
        }

        return $this->elements[$name];
    }
}
