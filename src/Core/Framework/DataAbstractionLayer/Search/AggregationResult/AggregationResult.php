<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\Struct\Struct;

class AggregationResult extends Struct
{
    /**
     * @var Aggregation
     */
    private $aggregation;

    /**
     * @var AbstractAggregationResult[]
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

    public function get(?array $key): ?AbstractAggregationResult
    {
        foreach ($this->result as $result) {
            if ($result->getKey() === $key) {
                return $result;
            }
        }

        return null;
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
