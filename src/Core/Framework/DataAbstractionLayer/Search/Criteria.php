<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
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
 * @template IDStructure of string|array<string, string> = string
 *
 * @final
 */
#[Package('framework')]
class Criteria extends Struct implements \Stringable
{
    use StateAwareTrait;

    final public const STATE_ELASTICSEARCH_AWARE = 'elasticsearchAware';

    final public const STATE_DISABLE_SEARCH_INFO = 'disableSearchInfo';

    final public const STATE_SCORE_RANKED_GROUPING = 'scoreRankedGrouping';

    final public const SCORE_FIELD = '_score';

    /**
     * no total count will be selected. Should be used if no pagination required (fastest)
     */
    final public const TOTAL_COUNT_MODE_NONE = 0;

    /**
     * exact total count will be selected. Should be used if an exact pagination is required (slow)
     */
    final public const TOTAL_COUNT_MODE_EXACT = 1;

    /**
     * fetches limit * 6 + 1 rows and returns a bounded lookahead count. Should be used if pagination
     * can work with "next page exists" (fast), but does not need an exact total.
     */
    final public const TOTAL_COUNT_MODE_NEXT_PAGES = 2;

    final public const ROOT_NESTING_LEVEL = 0;

    /**
     * @var list<FieldSorting>
     */
    protected array $sorting = [];

    /**
     * @var array<array-key, Filter>
     */
    protected array $filters = [];

    /**
     * @var list<Filter>
     */
    protected array $postFilters = [];

    /**
     * @var array<string, Aggregation>
     */
    protected array $aggregations = [];

    /**
     * @var list<ScoreQuery>
     */
    protected array $queries = [];

    /**
     * @var list<FieldGrouping>
     */
    protected array $groupFields = [];

    protected ?int $offset = null;

    protected ?int $limit = null;

    protected int $totalCountMode = self::TOTAL_COUNT_MODE_NONE;

    /**
     * @var array<string, Criteria>
     */
    protected array $associations = [];

    /**
     * @var array<IDStructure>
     */
    protected array $ids = [];

    protected bool $inherited = false;

    protected ?string $term = null;

    /**
     * @var array<string, list<string>>|null
     */
    protected ?array $includes = null;

    /**
     * @var array<string, list<string>>|null
     */
    protected ?array $excludes = null;

    protected ?string $title = null;

    /**
     * @var list<string>
     */
    protected array $fields = [];

    /**
     * @var list<string>
     */
    protected array $excludedFields = [];

    /**
     * @param array<IDStructure>|null $ids
     */
    public function __construct(?array $ids = null, protected int $nestingLevel = 0)
    {
        if ($ids === null) {
            return;
        }

        $ids = array_filter($ids);

        $this->validateIds($ids);

        $this->ids = $ids;
    }

    public function __toString(): string
    {
        $parsed = (new CriteriaArrayConverter(new AggregationParser()))->convert($this);

        return Json::encode($parsed);
    }

    /**
     * @return array<IDStructure>
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

    public function getNextPagesLimit(): int
    {
        // Fetch the current page and the next five pages so pagination can render a bounded six-page
        // window without an exact COUNT query. The additional row is a sentinel: its presence means
        // there are results beyond that window, so the exact last page is unknown.
        return (int) $this->limit * 6 + 1;
    }

    public function getTotalCountMode(): int
    {
        return $this->totalCountMode;
    }

    /**
     * @return list<FieldSorting>
     */
    public function getSorting(): array
    {
        return $this->sorting;
    }

    /**
     * @return array<string, Aggregation>
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
     * @return array<array-key, Filter>
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
        return array_filter($this->filters, static fn (Filter $filter) /* EqualsFilter $filter */ => $filter instanceof EqualsFilter && $filter->getField() === $field) !== [];
    }

    /**
     * @return list<Filter>
     */
    public function getPostFilters(): array
    {
        return $this->postFilters;
    }

    /**
     * @return list<ScoreQuery>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * @return array<string, Criteria>
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
                $criteria->associations[$part] = new Criteria(nestingLevel: $this->nestingLevel + 1);
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
     * @param array<string> $paths
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
     * @param array<IDStructure> $ids
     */
    public function setIds(array $ids): self
    {
        $this->validateIds($ids);
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
     * @param array<IDStructure> $ids
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
        $self->excludedFields = $this->excludedFields;

        return $self;
    }

    public function addGroupField(FieldGrouping $grouping): self
    {
        $this->groupFields[] = $grouping;

        return $this;
    }

    /**
     * @return list<FieldGrouping>
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
     * Allowlist of properties per api alias that the API serializes. Shapes the response only, the data is
     * still read from the database — use {@see addFields()} to skip it there as well.
     *
     * @param array<string, list<string>>|null $includes
     */
    public function setIncludes(?array $includes): void
    {
        $this->includes = $includes;
    }

    /**
     * @return array<string, list<string>>|null
     */
    public function getIncludes(): ?array
    {
        return $this->includes;
    }

    /**
     * Denylist of properties per api alias that the API serializes. Shapes the response only, the data is
     * still read from the database — use {@see excludeFields()} to skip it there as well.
     *
     * @param array<string, list<string>>|null $excludes
     */
    public function setExcludes(?array $excludes): void
    {
        $this->excludes = $excludes;
    }

    /**
     * @return array<string, list<string>>|null
     */
    public function getExcludes(): ?array
    {
        return $this->excludes;
    }

    public function getApiAlias(): string
    {
        return 'dal_criteria';
    }

    public function useIdSorting(): bool
    {
        if ($this->getIds() === []) {
            return false;
        }

        // manual sorting provided
        if ($this->getSorting() !== []) {
            return false;
        }

        // result will be sorted by interpreted search term and the calculated ranking
        if (($this->getTerm() ?? '') !== '') {
            return false;
        }

        // result will be sorted by calculated ranking
        if ($this->getQueries() !== []) {
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

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Allowlist of storage fields to read. Reduces the read itself, but returns PartialEntity instances in a
     * generic EntityCollection instead of the classes of the definition. Not to be confused with
     * {@see setIncludes()}, which only shapes the API response.
     *
     * @param list<string> $fields
     */
    public function addFields(array $fields): self
    {
        if ($this->excludedFields !== []) {
            throw DataAbstractionLayerException::criteriaFieldsAndExcludedFieldsAreMutuallyExclusive();
        }

        $this->fields = array_merge($this->fields, $fields);

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Drops the allowlist added via {@see addFields()}, so the read returns the entity and collection class of
     * the definition again instead of PartialEntity instances. Leaves {@see excludeFields()} untouched, as the
     * denylist keeps the typed entity.
     */
    public function resetFields(): self
    {
        $this->fields = [];

        return $this;
    }

    /**
     * Denylist counterpart to {@see addFields()}: loads the full, typed entity but omits the given
     * storage fields. Cannot be combined with addFields(); required and write-protected fields cannot be excluded.
     * Not to be confused with {@see setExcludes()}, which only shapes the API response.
     *
     * @param list<string> $fields
     */
    public function excludeFields(array $fields): self
    {
        if ($this->fields !== []) {
            throw DataAbstractionLayerException::criteriaFieldsAndExcludedFieldsAreMutuallyExclusive();
        }

        $this->excludedFields = array_merge($this->excludedFields, $fields);

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getExcludedFields(): array
    {
        return $this->excludedFields;
    }

    /**
     * Drops the denylist added via {@see excludeFields()}, so the read covers all storage fields again. Leaves
     * {@see addFields()} untouched, {@see resetFields()} drops that allowlist.
     */
    public function resetExcludedFields(): self
    {
        $this->excludedFields = [];

        return $this;
    }

    /**
     * Returns the nesting level of the criteria inside a criteria
     */
    public function getNestingLevel(): int
    {
        return $this->nestingLevel;
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
            foreach ($part as $item) {
                foreach ($item->getFields() as $field) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * @param array<mixed> $ids
     */
    private function validateIds(array $ids): void
    {
        if ($ids === []) {
            throw DataAbstractionLayerException::invalidCriteriaIds($ids, 'Ids should not be empty');
        }

        foreach ($ids as $id) {
            if (!\is_string($id) && !\is_array($id)) {
                throw DataAbstractionLayerException::invalidCriteriaIds($ids, 'Ids should be a list of strings or a list of key value pairs, for entities with combined primary keys');
            }

            if (!\is_array($id)) {
                continue;
            }

            foreach ($id as $key => $value) {
                if (!\is_string($key) || !\is_string($value)) {
                    throw DataAbstractionLayerException::invalidCriteriaIds($ids, 'Ids should be a list of strings or a list of key value pairs, for entities with combined primary keys');
                }
            }
        }
    }
}
