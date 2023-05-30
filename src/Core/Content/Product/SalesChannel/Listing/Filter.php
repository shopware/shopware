<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter as DALFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('inventory')]
class Filter extends Struct
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var bool
     */
    protected $filtered;

    /**
     * @var Aggregation[]
     */
    protected $aggregations;

    /**
     * @var DALFilter
     */
    protected $filter;

    /**
     * @var bool
     */
    protected $exclude;

    /**
     * @param int|float|string|bool|array|null $values
     */
    public function __construct(
        string $name,
        bool $filtered,
        array $aggregations,
        DALFilter $filter,
        protected $values,
        bool $exclude = true
    ) {
        $this->name = $name;
        $this->filtered = $filtered;
        $this->aggregations = $aggregations;
        $this->filter = $filter;
        $this->exclude = $exclude;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isFiltered(): bool
    {
        return $this->filtered;
    }

    /**
     * @return Aggregation[]
     */
    public function getAggregations(): array
    {
        return $this->aggregations;
    }

    public function getFilter(): DALFilter
    {
        return $this->filter;
    }

    /**
     * @return int|float|string|bool|array|null
     */
    public function getValues()
    {
        return $this->values;
    }

    public function exclude(): bool
    {
        return $this->exclude;
    }
}
