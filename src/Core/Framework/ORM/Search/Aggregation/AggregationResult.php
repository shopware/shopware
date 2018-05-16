<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search\Aggregation;

class AggregationResult
{
    /**
     * @var Aggregation
     */
    protected $aggregation;

    /**
     * @var mixed
     */
    protected $result;

    public function __construct(Aggregation $aggregation, $result)
    {
        $this->aggregation = $aggregation;
        $this->result = $result;
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

    public function getResult()
    {
        return $this->result;
    }
}
