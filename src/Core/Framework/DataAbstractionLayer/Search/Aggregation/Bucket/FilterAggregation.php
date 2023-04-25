<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class FilterAggregation extends BucketAggregation
{
    /**
     * @param Filter[] $filter
     */
    public function __construct(
        string $name,
        Aggregation $aggregation,
        protected array $filter
    ) {
        parent::__construct($name, '', $aggregation);
    }

    /**
     * @return Filter[]
     */
    public function getFilter(): array
    {
        return $this->filter;
    }

    public function getFields(): array
    {
        $fields = $this->aggregation?->getFields() ?? [];

        foreach ($this->filter as $filter) {
            $nested = $filter->getFields();
            foreach ($nested as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * @param Filter[] $filters
     */
    public function addFilters(array $filters): void
    {
        foreach ($filters as $filter) {
            $this->filter[] = $filter;
        }
    }
}
