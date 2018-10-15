<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search\Aggregation;

abstract class AggregationResult implements \JsonSerializable
{
    /**
     * @var Aggregation
     */
    private $aggregation;

    public function __construct(Aggregation $aggregation)
    {
        $this->aggregation = $aggregation;
    }

    abstract public function getResult(): array;

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

    public function jsonSerialize(): array
    {
        return $this->getResult();
    }
}
