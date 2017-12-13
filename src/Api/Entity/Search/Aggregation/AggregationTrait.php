<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search\Aggregation;

trait AggregationTrait
{
    /**
     * @var string
     */
    protected $field;

    /**
     * @var string
     */
    protected $name;

    public function getField(): string
    {
        return $this->field;
    }

    public function getFields(): array
    {
        return [$this->field];
    }

    public function getName(): string
    {
        return $this->name;
    }
}
