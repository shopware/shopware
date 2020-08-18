<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;

class FilterAggregation extends BucketAggregation
{
    /**
     * @var Filter[]
     */
    protected $filter;

    public function __construct(string $name, Aggregation $aggregation, array $filter)
    {
        parent::__construct($name, '_', $aggregation);
        $this->filter = $filter;
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
        $fields = $this->aggregation->getFields();

        foreach ($this->filter as $filter) {
            $nested = $filter->getFields();
            foreach ($nested as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }
}
