<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation;

use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaPartInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;

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

    /**
     * @var CriteriaPartInterface[]
     */
    protected $filters = [];

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

    public function addFilter(CriteriaPartInterface $filter): void
    {
        $this->filters[] = $filter;
    }

    public function resetFilter(): void
    {
        $this->filters = [];
    }

    public function getFilter(): ?MultiFilter
    {
        if (empty($this->filters)) {
            return null;
        }

        return new MultiFilter(MultiFilter::CONNECTION_AND, $this->filters);
    }
}
