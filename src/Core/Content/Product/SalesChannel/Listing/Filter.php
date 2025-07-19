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
     * @param list<Aggregation> $aggregations
     * @param int|float|string|bool|array<mixed>|null $values
     */
    public function __construct(
        protected string $name,
        protected bool $filtered,
        protected array $aggregations,
        protected DALFilter $filter,
        protected int|float|string|bool|array|null $values,
        protected bool $exclude = true
    ) {
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
     * @return list<Aggregation>
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
     * @return int|float|string|bool|array<mixed>|null
     */
    public function getValues(): int|float|string|bool|array|null
    {
        return $this->values;
    }

    public function exclude(): bool
    {
        return $this->exclude;
    }
}
