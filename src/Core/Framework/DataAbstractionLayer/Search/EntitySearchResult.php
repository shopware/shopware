<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\Deprecation\BCChange\ClassHierarchyChange;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\StateAwareTrait;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @final
 *
 * @template TEntityCollection of EntityCollection
 *
 * @phpstan-type TElement template-type<TEntityCollection, EntityCollection, 'TElement'>
 *
 * @extends EntityCollection<TElement>
 */
#[Package('framework')]
#[ClassHierarchyChange(version: 'v6.8.0', description: 'Will no longer extend EntityCollection, but will keep extending Struct.', newParentClass: Struct::class)]
class EntitySearchResult extends EntityCollection implements \JsonSerializable
{
    use StateAwareTrait;

    /**
     * @deprecated tag:v6.8.0 - Will become readonly in v6.8.0.
     */
    protected AggregationResultCollection $aggregations;

    /**
     * @deprecated tag:v6.8.0 - Will become readonly in v6.8.0.
     */
    protected int $page;

    /**
     * @deprecated tag:v6.8.0 - Will become readonly in v6.8.0.
     */
    protected ?int $limit = null;

    /**
     * @param TEntityCollection $entities
     */
    final public function __construct(
        /**
         * @deprecated tag:v6.8.0 - Will become readonly in v6.8.0.
         */
        protected string $entity,
        /**
         * @deprecated tag:v6.8.0 - Will become readonly in v6.8.0.
         */
        protected int $total,
        /**
         * @deprecated tag:v6.8.0 - Will become readonly in v6.8.0.
         *
         * @var TEntityCollection
         */
        protected EntityCollection $entities,
        ?AggregationResultCollection $aggregations,
        /**
         * @deprecated tag:v6.8.0 - Will become readonly in v6.8.0.
         */
        protected Criteria $criteria,
        /**
         * @deprecated tag:v6.8.0 - Will become readonly in v6.8.0.
         */
        protected Context $context,
    ) {
        $firstEntity = $entities->first();
        \assert($firstEntity === null || $entity === $firstEntity->getApiAlias(), 'The entity name must match the entity collection.');

        $this->aggregations = $aggregations ?? new AggregationResultCollection();
        $this->limit = $criteria->getLimit();
        $this->page = !$criteria->getLimit() ? 1 : (int) ceil((($criteria->getOffset() ?? 0) + 1) / $criteria->getLimit());

        Feature::silent('v6.8.0.0', fn () => parent::__construct($entities));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->filter() instead.
     */
    public function filter(\Closure $closure): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->filter()'));

        return Feature::silent('v6.8.0.0', fn () => $this->createNew($this->entities->filter($closure)));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->slice() instead.
     */
    public function slice(int $offset, ?int $length = null): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->slice()'));

        return Feature::silent('v6.8.0.0', fn () => $this->createNew($this->entities->slice($offset, $length)));
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @return TEntityCollection
     */
    public function getEntities(): EntityCollection
    {
        return $this->entities;
    }

    public function getAggregations(): AggregationResultCollection
    {
        return $this->aggregations;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed; the result wrapper becomes immutable.
     */
    public function clear(): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0'));

        parent::clear();

        $this->entities->clear();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed; the result wrapper becomes immutable.
     */
    public function add($entity): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0'));

        Feature::silent('v6.8.0.0', fn () => parent::add($entity));

        $this->entities->add($entity);
    }

    public function jsonSerialize(): array
    {
        $vars = get_object_vars($this);

        unset($vars['criteria']);
        unset($vars['context']);
        unset($vars['entities']);

        $this->convertDateTimePropertiesToJsonStringRepresentation($vars);

        return $vars;
    }

    public function getApiAlias(): string
    {
        return 'dal_entity_search_result';
    }

    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed; the result wrapper becomes immutable.
     */
    public function setPage(int $page): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0'));

        $this->page = $page;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed; the result wrapper becomes immutable.
     */
    public function setLimit(int $limit): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0'));

        $this->limit = $limit;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed; the property becomes readonly.
     */
    public function setEntity(string $entity): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0'));

        $this->entity = $entity;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->getAt() instead.
     */
    public function getAt(int $position)
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->getAt()'));

        return $this->entities->getAt($position);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->fill() instead.
     */
    public function fill(array $entities): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->fill()'));

        Feature::silent('v6.8.0.0', fn () => parent::fill($entities));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->set() instead.
     */
    public function set($key, $element): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->set()'));

        parent::set($key, $element);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->get() instead.
     */
    public function get($key)
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->get()'));

        return parent::get($key);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->count() instead.
     */
    public function count(): int
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->count()'));

        return parent::count();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->isEmpty() instead.
     */
    public function isEmpty(): bool
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->isEmpty()'));

        return parent::isEmpty();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->getKeys() instead.
     */
    public function getKeys(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->getKeys()'));

        return parent::getKeys();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->has() instead.
     */
    public function has($key): bool
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->has()'));

        return parent::has($key);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->map() instead.
     */
    public function map(\Closure $closure): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->map()'));

        return parent::map($closure);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->reduce() instead.
     */
    public function reduce(\Closure $closure, $initial = null)
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->reduce()'));

        return parent::reduce($closure, $initial);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->fmap() instead.
     */
    public function fmap(\Closure $closure): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->fmap()'));

        return parent::fmap($closure);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->flatMap() instead.
     */
    public function flatMap(\Closure $closure): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->flatMap()'));

        return parent::flatMap($closure);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->sort() instead.
     */
    public function sort(\Closure $closure): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->sort()'));

        parent::sort($closure);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->filterInstance() instead.
     */
    public function filterInstance(string $class): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->filterInstance()'));

        return parent::filterInstance($class);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->getElements() instead.
     */
    public function getElements(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->getElements()'));

        return parent::getElements();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->first() instead.
     */
    public function first()
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->first()'));

        return parent::first();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->firstWhere() instead.
     */
    public function firstWhere(\Closure $closure)
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->firstWhere()'));

        return parent::firstWhere($closure);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->last() instead.
     */
    public function last()
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->last()'));

        return parent::last();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->remove() instead.
     */
    public function remove($key): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->remove()'));

        parent::remove($key);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities() instead.
     */
    public function getIterator(): \Traversable
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()'));

        return parent::getIterator();
    }

    /**
     * @deprecated tag:v6.8.0 - Will no longer add entities to the result; the inherited Struct::assignRecursive() applies instead and has no effect on the readonly result. Use getEntities()->assignRecursive() instead.
     */
    public function assignRecursive(array $options): static
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            \sprintf(
                'Method "%s::assignRecursive()" is deprecated. As of v6.8.0.0 it will no longer add entities to the result, but fall back to "Struct::assignRecursive()", which has no effect on the readonly result. To add entities, use "getEntities()->assignRecursive()" instead.',
                static::class
            )
        );

        return parent::assignRecursive($options);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->getIds() instead.
     */
    public function getIds(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->getIds()'));

        return parent::getIds();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->filterByProperty() instead.
     */
    public function filterByProperty(string $property, $value): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->filterByProperty()'));

        return parent::filterByProperty($property, $value);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->filterAndReduceByProperty() instead.
     */
    public function filterAndReduceByProperty(string $property, $value): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->filterAndReduceByProperty()'));

        return parent::filterAndReduceByProperty($property, $value);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->merge() instead.
     */
    public function merge(EntityCollection $collection): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->merge()'));

        Feature::silent('v6.8.0.0', fn () => parent::merge($collection));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->insert() instead.
     */
    public function insert(int $position, Entity $entity): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->insert()'));

        parent::insert($position, $entity);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->getList() instead.
     */
    public function getList(array $ids): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->getList()'));

        return parent::getList($ids);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->sortByIdArray() instead.
     */
    public function sortByIdArray(array $ids): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->sortByIdArray()'));

        parent::sortByIdArray($ids);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->getCustomFieldsValues() instead.
     */
    public function getCustomFieldsValues(string ...$fields): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->getCustomFieldsValues()'));

        return parent::getCustomFieldsValues(...$fields);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->getCustomFieldsValue() instead.
     */
    public function getCustomFieldsValue(string $field): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->getCustomFieldsValue()'));

        return parent::getCustomFieldsValue($field);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->setCustomFields() instead.
     */
    public function setCustomFields(array $values): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0', 'getEntities()->setCustomFields()'));

        parent::setCustomFields($values);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed.
     *
     * @param iterable<TElement> $elements
     *
     * @return static<TEntityCollection>
     */
    protected function createNew(iterable $elements = []): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(static::class, __FUNCTION__, 'v6.8.0.0'));

        if (!$elements instanceof EntityCollection) {
            $elements = new EntityCollection($elements);
        }

        return new static(
            $this->entity,
            $elements->count(),
            $elements,
            $this->aggregations,
            $this->criteria,
            $this->context
        );
    }
}
