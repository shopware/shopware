<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation;

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

    /**
     * @var string[]
     */
    protected $groupByFields = [];

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

    public function getGroupByFields(): array
    {
        return $this->groupByFields;
    }
}
