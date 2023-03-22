<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\StateAwareTrait;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Util\Json;

/**
 * @final
 */
#[Package('core')]
class Criteria extends Struct implements \Stringable
{
    use StateAwareTrait;
    final public const STATE_ELASTICSEARCH_AWARE = 'elasticsearchAware';

    /**
     * no total count will be selected. Should be used if no pagination required (fastest)
     */
    final public const TOTAL_COUNT_MODE_NONE = 0;

    /**
     * exact total count will be selected. Should be used if an exact pagination is required (slow)
     */
    final public const TOTAL_COUNT_MODE_EXACT = 1;

    /**
     * fetches limit * 5 + 1. Should be used if pagination can work with "next page exists" (fast)
     */
    final public const TOTAL_COUNT_MODE_NEXT_PAGES = 2;

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
     * @var array<string>|array<int, array<string>>
     */
    protected $ids = [];

    /**
     * @var bool
     */
    protected $inherited = false;

    /**
     * @var string|null
     */
    protected $term;

    /**
     * @var array<string, array<string, string>>|null
     */
    protected $includes;

    /**
     * @var string|null
     */
    protected $title;

    /**
     * @var string[]
     */
    protected array $fields = [];

    /**
     * @param array<string|array<string>>|null $ids
     */
    public function __construct(?array $ids = null)
    {
        if ($ids === null) {
            return;
        }

        $ids = array_filter($ids);
        if (empty($ids)) {
            throw new \RuntimeException('Empty ids provided in criteria');
        }

        $this->ids = $ids;
    }

    public function __toString(): string
    {
        $parsed = (new CriteriaArrayConverter(new AggregationParser()))->convert($this);

        return Json::encode($parsed);
    }

    /**
     * @return array<string>|array<int, array<string>>
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

    /**
     * @param string $field
     */
    public function hasEqualsFilter($field): bool
    {
        return \count(array_filter($this->filters, static fn (Filter $filter) /* EqualsFilter $filter */ => $filter instanceof EqualsFilter && $filter->getField() === $field)) > 0;
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

    public function setFilter(string $key, Filter $filter): self
    {
        $this->filters[$key] = $filter;

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
     * @param string[] $paths
     *
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

    /**
     * @return array<string>
     */
    public function getAggregationQueryFields(): array
    {
        return $this->collectFields([
            $this->filters,
            $this->queries,
        ]);
    }

    /**
     * @return array<string>
     */
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

    /**
     * @return array<string>
     */
    public function getFilterFields(): array
    {
        return $this->collectFields([
            $this->filters,
            $this->postFilters,
        ]);
    }

    /**
     * @return array<string>
     */
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
     * @param array<string>|array<int, array<string>> $ids
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
     * @param array<string>|array<int, array<string>> $ids
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
        $self->fields = $this->fields;

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

    /**
     * @param array<string, array<string, string>>|null $includes
     */
    public function setIncludes(?array $includes): void
    {
        $this->includes = $includes;
    }

    /**
     * @return array<string, array<string, string>>|null
     */
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

    /**
     * @param string[] $fields
     *
     * @internal
     */
    public function addFields(array $fields): self
    {
        $this->fields = array_merge($this->fields, $fields);

        return $this;
    }

    /**
     * @return string[]
     *
     * @internal
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @param array<array<CriteriaPartInterface>> $parts
     *
     * @return array<string>
     */
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
