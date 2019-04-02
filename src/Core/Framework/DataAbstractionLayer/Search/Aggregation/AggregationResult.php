<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation;

use Shopware\Core\Framework\Struct\Struct;

class AggregationResult extends Struct
{
    /**
     * @var Aggregation
     */
    private $aggregation;

    /**
     * @var array
     */
    private $result;

    public function __construct(Aggregation $aggregation, array $result)
    {
        $this->aggregation = $aggregation;
        $this->result = $result;
    }

    public function getResult(): array
    {
        return $this->result;
    }

    public function getResultByKey(?array $key): ?array
    {
        $index = \array_search($key, array_column($this->result, 'key'), true);

        if ($index === false) {
            return null;
        }

        return $this->result[$index];
    }

    public function getName(): string
    {
        return $this->aggregation->getName();
    }

    public function getField(): string
    {
        return $this->aggregation->getField();
    }

    public function getAggregation(): Aggregation
    {
        return $this->aggregation;
    }
}
