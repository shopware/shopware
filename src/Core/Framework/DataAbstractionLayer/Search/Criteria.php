<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Struct\StateAwareTrait;
use Shopware\Core\Framework\Struct\Struct;

class Criteria extends Struct
{
    use StateAwareTrait;

    /**
     * no total count will be selected. Should be used if no pagination required (fastest)
     */
    public const TOTAL_COUNT_MODE_NONE = 0;

    /**
     * exact total count will be selected. Should be used if an exact pagination is required (slow)
     */
    public const TOTAL_COUNT_MODE_EXACT = 1;

    /**
     * fetches limit * 5 + 1. Should be used if pagination can work with "next page exists" (fast)
     */
    public const TOTAL_COUNT_MODE_NEXT_PAGES = 2;

    /**
     * @var FieldSorting[]
     */
    protected $sorting = [];

    /**
     * @var Filter[]
     */
    protected $filters = [];

    /**
     * @var Filter[]
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
     * @var FieldGrouping[]
     */
    protected $groupFields = [];

    /**
     * @var int|null
     */
    protected $offset;

    /**
     * @var int|null
     */
    protected $limit;

    /**
     * @var int
     */
    protected $totalCountMode = self::TOTAL_COUNT_MODE_NONE;

    /**
     * @var Criteria[]
     */
    protected $associations = [];

    /**
     * @var string[]|array<int, string[]>
     */
    protected $ids;

    /**
     * @var bool
     */
    protected $inherited = false;

    /**
     * @var string|null
     */
    protected $term;

    /**
     * @var array|null
     */
    protected $includes;

    /**
     * @var string|null
     */
    protected $title;

    /**
     * @param string[]|array<int, string[]> $ids
     *
     * @throws InconsistentCriteriaIdsException
     */
    public function __construct(array $ids = [])
    {
        if (\count($ids) > \count(array_filter($ids))) {
            throw new InconsistentCriteriaIdsException();
        }

        $this->ids = $ids;
    }

    /**
     * @return string[]|array<int, string[]>
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getTotalCountMode(): int
    {
        return $this->totalCountMode;
    }

    /**
     * @return FieldSorting[]
     */
    public function getSorting(): array
    {
        return $this->sorting;
    }

    /**
     * @return Aggregation[]
     */
    public function getAggregations(): array
    {
        return $this->aggregations;
    }

    public function getAggregation(string $name): ?Aggregation
    {
        return $this->aggregations[$name] ?? null;
    }

    /**
     * @return Filter[]
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function hasEqualsFilter($field): bool
    {
        return \count(array_filter($this->filters, static function (Filter $filter) use ($field) {
            /* EqualsFilter $filter */
            return $filter instanceof EqualsFilter && $filter->getField() === $field;
        })) > 0;
    }

    /**
     * @return Filter[]
     */
    public function getPostFilters(): array
    {
        return $this->postFilters;
    }

    /**
     * @return ScoreQuery[]
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * @return Criteria[]
     */
    public function getAssociations(): array
    {
        return $this->associations;
    }

    /**
     * Returns the criteria for the provided association path. Also supports nested paths
     *
     * e.g `$criteria->getAssociation('categories.media.thumbnails')`
     *
     * @throws InconsistentCriteriaIdsException
     */
    public function getAssociation(string $path): Criteria
    {
        $parts = explode('.', $path);

        $criteria = $this;
        foreach ($parts as $part) {
            if ($part === 'extensions') {
                continue;
            }

            if (!$criteria->hasAssociation($part)) {
                $criteria->associations[$part] = new Criteria();
            }

            $criteria = $criteria->associations[$part];
        }

        return $criteria;
    }

    public function addFilter(Filter ...$queries): self
    {
        foreach ($queries as $query) {
            $this->filters[] = $query;
        }

        return $this;
    }

    public function addSorting(FieldSorting ...$sorting): self
    {
        foreach ($sorting as $sort) {
            $this->sorting[] = $sort;
        }

        return $this;
    }

    public function addAggregation(Aggregation ...$aggregations): self
    {
        foreach ($aggregations as $aggregation) {
            $this->aggregations[$aggregation->getName()] = $aggregation;
        }

        return $this;
    }

    public function addPostFilter(Filter ...$queries): self
    {
        foreach ($queries as $query) {
            $this->postFilters[] = $query;
        }

        return $this;
    }

    public function addQuery(ScoreQuery ...$queries): self
    {
        foreach ($queries as $query) {
            $this->queries[] = $query;
        }

        return $this;
    }

    /**
     * Add for each part of the provided path an association
     *
     * e.g
     *
     * $criteria->addAssociation('categories.media.thumbnails')
     *
     * @throws InconsistentCriteriaIdsException
     */
    public function addAssociation(string $path): self
    {
        $parts = explode('.', $path);

        $criteria = $this;
        foreach ($parts as $part) {
            if (mb_strtolower($part) === 'extensions') {
                continue;
            }

            $criteria = $criteria->getAssociation($part);
        }

        return $this;
    }

    /**
     * Allows to add multiple associations paths
     *
     * e.g.:
     *
     * $criteria->addAssociations([
     *      'prices',
     *      'cover.media',
     *      'categories.cover.media'
     * ]);
     *
     * @throws InconsistentCriteriaIdsException
     */
    public function addAssociations(array $paths): self
    {
        foreach ($paths as $path) {
            $this->addAssociation($path);
        }

        return $this;
    }

    public function hasAssociation(string $field): bool
    {
        return isset($this->associations[$field]);
    }

    public function resetSorting(): self
    {
        $this->sorting = [];

        return $this;
    }

    public function resetAssociations(): self
    {
        $this->associations = [];

        return $this;
    }

    public function resetQueries(): self
    {
        $this->queries = [];

        return $this;
    }

    public function resetFilters(): self
    {
        $this->filters = [];

        return $this;
    }

    public function resetPostFilters(): self
    {
        $this->postFilters = [];

        return $this;
    }

    public function resetAggregations(): self
    {
        $this->aggregations = [];

        return $this;
    }

    public function setTotalCountMode(int $totalCountMode): self
    {
        $this->totalCountMode = $totalCountMode;

        return $this;
    }

    public function setLimit(?int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function setOffset(?int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    public function getAggregationQueryFields(): array
    {
        return $this->collectFields([
            $this->filters,
            $this->queries,
        ]);
    }

    public function getSearchQueryFields(): array
    {
        return $this->collectFields([
            $this->filters,
            $this->postFilters,
            $this->sorting,
            $this->queries,
            $this->groupFields,
        ]);
    }

    public function getFilterFields(): array
    {
        return $this->collectFields([
            $this->filters,
            $this->postFilters,
        ]);
    }

    public function getAllFields(): array
    {
        return $this->collectFields([
            $this->filters,
            $this->postFilters,
            $this->sorting,
            $this->queries,
            $this->groupFields,
            $this->aggregations,
        ]);
    }

    /**
     * @param string[]|array<int, string[]> $ids
     */
    public function setIds(array $ids): self
    {
        $this->ids = $ids;

        return $this;
    }

    public function getTerm(): ?string
    {
        return $this->term;
    }

    public function setTerm(?string $term): self
    {
        $this->term = $term;

        return $this;
    }

    /**
     * @param string[]|array<int, string[]> $ids
     */
    public function cloneForRead(array $ids = []): Criteria
    {
        $self = new self($ids);
        $self->setTitle($this->getTitle());

        $associations = [];

        foreach ($this->associations as $name => $association) {
            $associations[$name] = clone $association;
        }

        $self->associations = $associations;

        return $self;
    }

    public function addGroupField(FieldGrouping $grouping): self
    {
        $this->groupFields[] = $grouping;

        return $this;
    }

    /**
     * @return FieldGrouping[]
     */
    public function getGroupFields(): array
    {
        return $this->groupFields;
    }

    public function resetGroupFields(): self
    {
        $this->groupFields = [];

        return $this;
    }

    public function setIncludes(?array $includes): void
    {
        $this->includes = $includes;
    }

    public function getIncludes()
    {
        return $this->includes;
    }

    public function getApiAlias(): string
    {
        return 'dal_criteria';
    }

    public function useIdSorting(): bool
    {
        if (empty($this->getIds())) {
            return false;
        }

        // manual sorting provided
        if (!empty($this->getSorting())) {
            return false;
        }

        // result will be sorted by interpreted search term and the calculated ranking
        if (!empty($this->getTerm())) {
            return false;
        }

        // result will be sorted by calculated ranking
        if (!empty($this->getQueries())) {
            return false;
        }

        return true;
    }

    public function removeAssociation(string $association): void
    {
        unset($this->associations[$association]);
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    private function collectFields(array $parts): array
    {
        $fields = [];

        foreach ($parts as $part) {
            /** @var CriteriaPartInterface $item */
            foreach ($part as $item) {
                foreach ($item->getFields() as $field) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }
}
