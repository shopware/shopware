<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation;

use Shopware\Core\Framework\Struct\Struct;

abstract class AggregationResult extends Struct
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
}
