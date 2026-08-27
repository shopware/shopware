<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Deprecation\BCChange\ClassHierarchyChange;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\StateAwareTrait;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @extends EntitySearchResult<ProductCollection>
 */
#[Package('inventory')]
#[ClassHierarchyChange(version: 'v6.8.0', description: 'Will no longer extend EntitySearchResult, but will keep extending Struct.', newParentClass: Struct::class)]
class ProductListingResult extends EntitySearchResult
{
    // Used here, not only inherited from EntitySearchResult: the listing keeps its states across the v6.8.0 hierarchy change
    use StateAwareTrait;

    protected ?string $sorting = null;

    /**
     * @var array<string, int|float|string|bool|array<mixed>|null>
     */
    protected array $currentFilters = [];

    protected ProductSortingCollection $availableSortings;

    protected ?string $streamId = null;

    /**
     * Declared here, not inherited: listing processors set the page and limit after construction, so the listing
     * result owns this state independently of the search result it was created from.
     */
    // @phpstan-ignore property.parentPropertyFinalByPhpDoc (the listing result keeps its own page state)
    protected int $page;

    // @phpstan-ignore property.parentPropertyFinalByPhpDoc (the listing result keeps its own limit state)
    protected ?int $limit = null;

    /**
     * @var EntitySearchResult<ProductCollection>|null
     */
    protected ?EntitySearchResult $source = null;

    /**
     * Construction entry point with a stable signature across the v6.8.0 cut. Callers that adopt this method now will keep working after the structural change.
     *
     * @param EntitySearchResult<ProductCollection> $result
     * @param array<string, int|float|string|bool|array<mixed>|null> $currentFilters
     */
    public static function fromSearchResult(
        EntitySearchResult $result,
        ?ProductSortingCollection $availableSortings = null,
        ?string $sorting = null,
        array $currentFilters = [],
        ?string $streamId = null,
    ): self {
        $instance = self::createFrom($result);

        $instance->source = $result;
        if ($availableSortings !== null) {
            $instance->availableSortings = $availableSortings;
        }
        $instance->sorting = $sorting;
        $instance->currentFilters = $currentFilters;
        $instance->streamId = $streamId;

        return $instance;
    }

    /**
     * Stable access to the underlying search result across the v6.8.0 cut. Use e.g. getSource()->getEntities() instead of the collection API inherited from EntitySearchResult.
     *
     * @return EntitySearchResult<ProductCollection>
     */
    public function getSource(): EntitySearchResult
    {
        // Instances built via createFrom() or the constructor carry the search result data themselves
        return $this->source ??= new EntitySearchResult(
            parent::getEntity(),
            parent::getTotal(),
            parent::getEntities(),
            parent::getAggregations(),
            parent::getCriteria(),
            parent::getContext(),
        );
    }

    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Intentionally not deprecated, unlike the parent method: listing processors modify the page after construction by design.
     */
    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * Intentionally not deprecated, unlike the parent method: listing processors modify the limit after construction by design.
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @param int|float|string|bool|array<mixed>|null $value
     */
    public function addCurrentFilter(string $key, $value): void
    {
        $this->currentFilters[$key] = $value;
    }

    public function getAvailableSortings(): ProductSortingCollection
    {
        return $this->availableSortings;
    }

    public function setAvailableSortings(ProductSortingCollection $availableSortings): void
    {
        $this->availableSortings = $availableSortings;
    }

    public function getSorting(): ?string
    {
        return $this->sorting;
    }

    public function setSorting(?string $sorting): void
    {
        $this->sorting = $sorting;
    }

    /**
     * @return array<string, int|float|string|bool|array<mixed>|null>
     */
    public function getCurrentFilters(): array
    {
        return $this->currentFilters;
    }

    /**
     * @return int|float|string|bool|array<mixed>|null
     */
    public function getCurrentFilter(string $key)
    {
        return $this->currentFilters[$key] ?? null;
    }

    public function getApiAlias(): string
    {
        return 'product_listing';
    }

    public function setStreamId(?string $streamId): void
    {
        $this->streamId = $streamId;
    }

    public function getStreamId(): ?string
    {
        return $this->streamId;
    }

    public function jsonSerialize(): array
    {
        $vars = parent::jsonSerialize();

        // The wrapped search result would duplicate the listing data in API responses
        unset($vars['source']);

        return $vars;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities() instead.
     *
     * @return ProductCollection
     */
    public function getEntities(): EntityCollection
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()'));

        return parent::getEntities();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getTotal() instead.
     */
    public function getTotal(): int
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getTotal()'));

        return parent::getTotal();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getAggregations() instead.
     */
    public function getAggregations(): AggregationResultCollection
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getAggregations()'));

        return parent::getAggregations();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getCriteria() instead.
     */
    public function getCriteria(): Criteria
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getCriteria()'));

        return parent::getCriteria();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getContext() instead.
     */
    public function getContext(): Context
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getContext()'));

        return parent::getContext();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntity() instead.
     */
    public function getEntity(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntity()'));

        return parent::getEntity();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->filter() instead.
     */
    public function filter(\Closure $closure): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->filter()'));

        return Feature::silent('v6.8.0.0', fn () => parent::filter($closure));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->slice() instead.
     */
    public function slice(int $offset, ?int $length = null): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->slice()'));

        return Feature::silent('v6.8.0.0', fn () => parent::slice($offset, $length));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->getAt() instead.
     */
    public function getAt(int $position)
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->getAt()'));

        return Feature::silent('v6.8.0.0', fn () => parent::getAt($position));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->fill() instead.
     */
    public function fill(array $entities): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->fill()'));

        Feature::silent('v6.8.0.0', fn () => parent::fill($entities));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->set() instead.
     */
    public function set($key, $element): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->set()'));

        Feature::silent('v6.8.0.0', fn () => parent::set($key, $element));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->get() instead.
     */
    public function get($key)
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->get()'));

        return Feature::silent('v6.8.0.0', fn () => parent::get($key));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->count() instead.
     */
    public function count(): int
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->count()'));

        return Feature::silent('v6.8.0.0', fn () => parent::count());
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->isEmpty() instead.
     */
    public function isEmpty(): bool
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->isEmpty()'));

        return Feature::silent('v6.8.0.0', fn () => parent::isEmpty());
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->getKeys() instead.
     */
    public function getKeys(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->getKeys()'));

        return Feature::silent('v6.8.0.0', fn () => parent::getKeys());
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->has() instead.
     */
    public function has($key): bool
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->has()'));

        return Feature::silent('v6.8.0.0', fn () => parent::has($key));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->map() instead.
     */
    public function map(\Closure $closure): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->map()'));

        return Feature::silent('v6.8.0.0', fn () => parent::map($closure));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->reduce() instead.
     */
    public function reduce(\Closure $closure, $initial = null)
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->reduce()'));

        return Feature::silent('v6.8.0.0', fn () => parent::reduce($closure, $initial));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->fmap() instead.
     */
    public function fmap(\Closure $closure): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->fmap()'));

        return Feature::silent('v6.8.0.0', fn () => parent::fmap($closure));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->flatMap() instead.
     */
    public function flatMap(\Closure $closure): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->flatMap()'));

        return Feature::silent('v6.8.0.0', fn () => parent::flatMap($closure));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->sort() instead.
     */
    public function sort(\Closure $closure): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->sort()'));

        Feature::silent('v6.8.0.0', fn () => parent::sort($closure));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->filterInstance() instead.
     */
    public function filterInstance(string $class): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->filterInstance()'));

        return Feature::silent('v6.8.0.0', fn () => parent::filterInstance($class));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->getElements() instead.
     */
    public function getElements(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->getElements()'));

        return Feature::silent('v6.8.0.0', fn () => parent::getElements());
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->first() instead.
     */
    public function first()
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->first()'));

        return Feature::silent('v6.8.0.0', fn () => parent::first());
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->firstWhere() instead.
     */
    public function firstWhere(\Closure $closure)
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->firstWhere()'));

        return Feature::silent('v6.8.0.0', fn () => parent::firstWhere($closure));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->last() instead.
     */
    public function last()
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->last()'));

        return Feature::silent('v6.8.0.0', fn () => parent::last());
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->remove() instead.
     */
    public function remove($key): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->remove()'));

        Feature::silent('v6.8.0.0', fn () => parent::remove($key));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities() instead.
     */
    public function getIterator(): \Traversable
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()'));

        return Feature::silent('v6.8.0.0', fn () => parent::getIterator());
    }

    /**
     * @deprecated tag:v6.8.0 - Will no longer add entities to the result; the inherited Struct::assignRecursive() applies instead and has no effect on the readonly result. Use getSource()->getEntities()->assignRecursive() instead.
     */
    public function assignRecursive(array $options): static
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            \sprintf(
                'Method "%s::assignRecursive()" is deprecated. As of v6.8.0.0 it will no longer add entities to the result, but fall back to "Struct::assignRecursive()", which has no effect on the readonly result. To add entities, use "getSource()->getEntities()->assignRecursive()" instead.',
                static::class
            )
        );

        return Feature::silent('v6.8.0.0', fn () => parent::assignRecursive($options));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->getIds() instead.
     */
    public function getIds(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->getIds()'));

        return Feature::silent('v6.8.0.0', fn () => parent::getIds());
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->filterByProperty() instead.
     */
    public function filterByProperty(string $property, $value): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->filterByProperty()'));

        return Feature::silent('v6.8.0.0', fn () => parent::filterByProperty($property, $value));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->filterAndReduceByProperty() instead.
     */
    public function filterAndReduceByProperty(string $property, $value): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->filterAndReduceByProperty()'));

        return Feature::silent('v6.8.0.0', fn () => parent::filterAndReduceByProperty($property, $value));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->merge() instead.
     */
    public function merge(EntityCollection $collection): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->merge()'));

        Feature::silent('v6.8.0.0', fn () => parent::merge($collection));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->insert() instead.
     */
    public function insert(int $position, Entity $entity): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->insert()'));

        Feature::silent('v6.8.0.0', fn () => parent::insert($position, $entity));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->getList() instead.
     */
    public function getList(array $ids): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->getList()'));

        return Feature::silent('v6.8.0.0', fn () => parent::getList($ids));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->sortByIdArray() instead.
     */
    public function sortByIdArray(array $ids): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->sortByIdArray()'));

        Feature::silent('v6.8.0.0', fn () => parent::sortByIdArray($ids));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->getCustomFieldsValues() instead.
     */
    public function getCustomFieldsValues(string ...$fields): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->getCustomFieldsValues()'));

        return Feature::silent('v6.8.0.0', fn () => parent::getCustomFieldsValues(...$fields));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->getCustomFieldsValue() instead.
     */
    public function getCustomFieldsValue(string $field): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->getCustomFieldsValue()'));

        return Feature::silent('v6.8.0.0', fn () => parent::getCustomFieldsValue($field));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getSource()->getEntities()->setCustomFields() instead.
     */
    public function setCustomFields(array $values): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getSource()->getEntities()->setCustomFields()'));

        Feature::silent('v6.8.0.0', fn () => parent::setCustomFields($values));
    }
}
