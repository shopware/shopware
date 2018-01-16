<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search;

use Shopware\Api\Entity\Search\Aggregation\Aggregation;
use Shopware\Api\Entity\Search\Query\NestedQuery;
use Shopware\Api\Entity\Search\Query\Query;
use Shopware\Api\Entity\Search\Query\ScoreQuery;
use Shopware\Api\Entity\Search\Sorting\FieldSorting;
use Shopware\Framework\Struct\Struct;

class Criteria extends Struct
{
    /**
     * @var FieldSorting[]
     */
    protected $sortings = [];

    /**
     * @var Query[]
     */
    protected $filters = [];

    /**
     * @var Query[]
     */
    protected $postFilters = [];

    /**
     * @var Aggregation[]
     */
    protected $aggregations = [];

    /**
     * @var ScoreQuery[]
     */
    protected $queries = [];

    /**
     * @var int|null
     */
    protected $offset;

    /**
     * @var int|null
     */
    protected $limit;

    /**
     * @var bool
     */
    protected $fetchCount = false;

    /**
     * @return FieldSorting[]
     */
    public function getSortings(): array
    {
        return $this->sortings;
    }

    /**
     * @return Aggregation[]
     */
    public function getAggregations(): array
    {
        return $this->aggregations;
    }

    public function getFilters(): NestedQuery
    {
        return new NestedQuery($this->filters);
    }

    public function getPostFilters(): NestedQuery
    {
        return new NestedQuery($this->postFilters);
    }

    public function getAllFilters(): NestedQuery
    {
        return new NestedQuery(array_merge($this->filters, $this->postFilters));
    }

    public function addFilter(Query $query): self
    {
        $this->filters[] = $query;

        return $this;
    }

    public function addSorting(FieldSorting $sorting): self
    {
        $this->sortings[] = $sorting;

        return $this;
    }

    public function addAggregation(Aggregation $aggregation): self
    {
        $this->aggregations[] = $aggregation;

        return $this;
    }

    public function addPostFilter(Query $query): self
    {
        $this->postFilters[] = $query;

        return $this;
    }

    public function addQuery(ScoreQuery $query): self
    {
        $this->queries[] = $query;

        return $this;
    }

    public function addQueries(array $queries): self
    {
        foreach ($queries as $query) {
            $this->addQuery($query);
        }

        return $this;
    }

    public function getSortingFields(): array
    {
        $fields = [];
        foreach ($this->sortings as $sorting) {
            foreach ($sorting->getFields() as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public function getAggregationFields(): array
    {
        $fields = [];
        foreach ($this->aggregations as $aggregation) {
            foreach ($aggregation->getFields() as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public function getPostFilterFields(): array
    {
        $fields = [];
        foreach ($this->postFilters as $filter) {
            foreach ($filter->getFields() as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public function getQueryFields(): array
    {
        $fields = [];
        foreach ($this->queries as $query) {
            foreach ($query->getFields() as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public function getFilterFields(): array
    {
        $fields = [];
        foreach ($this->filters as $filter) {
            foreach ($filter->getFields() as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function setOffset(?int $offset): void
    {
        $this->offset = $offset;
    }

    public function setLimit(?int $limit): void
    {
        $this->limit = $limit;
    }

    public function fetchCount(): bool
    {
        return $this->fetchCount;
    }

    public function setFetchCount(bool $fetchCount): void
    {
        $this->fetchCount = $fetchCount;
    }

    /**
     * @return ScoreQuery[]
     */
    public function getQueries()
    {
        return $this->queries;
    }
}
