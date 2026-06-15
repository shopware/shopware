<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\StateAwareTrait;

/**
 * @final
 *
 * @deprecated tag:v6.8.0 reason:class-hierarchy-change - Will no longer extend EntityCollection.
 *
 * @template TEntityCollection of EntityCollection
 *
 * @phpstan-type TElement template-type<TEntityCollection, EntityCollection, 'TElement'>
 *
 * @extends EntityCollection<TElement>
 */
#[Package('framework')]
class EntitySearchResult extends EntityCollection
{
    use StateAwareTrait;

    protected AggregationResultCollection $aggregations;

    protected int $page;

    protected ?int $limit = null;

    /**
     * @param TEntityCollection $entities
     */
    final public function __construct(
        protected string $entity,
        protected int $total,
        protected EntityCollection $entities,
        ?AggregationResultCollection $aggregations,
        protected Criteria $criteria,
        protected Context $context
    ) {
        $this->aggregations = $aggregations ?? new AggregationResultCollection();
        $this->limit = $criteria->getLimit();
        $this->page = !$criteria->getLimit() ? 1 : (int) ceil((($criteria->getOffset() ?? 0) + 1) / $criteria->getLimit());

        parent::__construct($entities);
    }

    /**
     * @deprecated tag:v6.8.0 reason:return-type-change - Will return EntityCollection instead of EntitySearchResult in v6.8.0.
     *
     * @return static<TEntityCollection>
     */
    public function filter(\Closure $closure): static
    {
        return $this->createNew($this->entities->filter($closure));
    }

    /**
     * @deprecated tag:v6.8.0 reason:return-type-change - Will return EntityCollection instead of EntitySearchResult in v6.8.0.
     *
     * @return static<TEntityCollection>
     */
    public function slice(int $offset, ?int $length = null): static
    {
        return $this->createNew($this->entities->slice($offset, $length));
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
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed; the result wrapper becomes immutable.
     */
    public function clear(): void
    {
        parent::clear();

        $this->entities->clear();
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed; the result wrapper becomes immutable.
     */
    public function add($entity): void
    {
        parent::add($entity);

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

    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed; pass the entity name at construction.
     */
    public function setEntity(string $entity): void
    {
        $this->entity = $entity;
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->getAt() instead.
     *
     * @return TElement|null
     */
    public function getAt(int $position)
    {
        return $this->entities->getAt($position);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->fill() instead.
     *
     * @param array<TElement> $entities
     */
    public function fill(array $entities): void
    {
        parent::fill($entities);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->set() instead.
     *
     * @param string|null $key
     * @param TElement $element
     */
    public function set($key, $element): void
    {
        parent::set($key, $element);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->get() instead.
     *
     * @param string $key
     *
     * @return TElement|null
     */
    public function get($key)
    {
        return parent::get($key);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->count() or getTotal() instead.
     */
    public function count(): int
    {
        return parent::count();
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->isEmpty() instead.
     */
    public function isEmpty(): bool
    {
        return parent::isEmpty();
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->getKeys() instead.
     *
     * @return list<string>
     */
    public function getKeys(): array
    {
        return parent::getKeys();
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->has() instead.
     *
     * @param string $key
     */
    public function has($key): bool
    {
        return parent::has($key);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->map() instead.
     *
     * @template TMapped
     *
     * @param \Closure(TElement): TMapped $closure
     *
     * @return array<string, TMapped>
     */
    public function map(\Closure $closure): array
    {
        return parent::map($closure);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->reduce() instead.
     *
     * @template TReduced
     *
     * @param \Closure(TReduced, TElement): TReduced $closure
     * @param TReduced $initial
     *
     * @return TReduced
     */
    public function reduce(\Closure $closure, $initial = null)
    {
        return parent::reduce($closure, $initial);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->fmap() instead.
     *
     * @template TMapped
     *
     * @param \Closure(TElement): (TMapped|false|null) $closure
     *
     * @return array<string, TMapped>
     */
    public function fmap(\Closure $closure): array
    {
        return parent::fmap($closure);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->flatMap() instead.
     *
     * @template TMapped
     *
     * @param \Closure(TElement): (TMapped|iterable<*, TMapped|null>|null) $closure
     *
     * @return array<string, TMapped>
     */
    public function flatMap(\Closure $closure): array
    {
        return parent::flatMap($closure);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->sort() instead.
     *
     * @param \Closure(TElement, TElement): int $closure
     */
    public function sort(\Closure $closure): void
    {
        parent::sort($closure);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->filterInstance() instead.
     *
     * @param class-string $class
     */
    public function filterInstance(string $class): static
    {
        return parent::filterInstance($class);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->getElements() instead.
     *
     * @return array<string, TElement>
     */
    public function getElements(): array
    {
        return parent::getElements();
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->first() instead.
     *
     * @return TElement|null
     */
    public function first()
    {
        return parent::first();
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->firstWhere() instead.
     *
     * @param \Closure(TElement): bool $closure
     *
     * @return TElement|null
     */
    public function firstWhere(\Closure $closure)
    {
        return parent::firstWhere($closure);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->last() instead.
     *
     * @return TElement|null
     */
    public function last()
    {
        return parent::last();
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->remove() instead.
     *
     * @param string $key
     */
    public function remove($key): void
    {
        parent::remove($key);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities() instead.
     *
     * @return \Traversable<TElement>
     */
    public function getIterator(): \Traversable
    {
        return parent::getIterator();
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->getIds() instead.
     *
     * @return array<string>
     */
    public function getIds(): array
    {
        return parent::getIds();
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->filterByProperty() instead.
     *
     * @param mixed $value
     */
    public function filterByProperty(string $property, $value): static
    {
        return parent::filterByProperty($property, $value);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->filterAndReduceByProperty() instead.
     *
     * @param mixed $value
     */
    public function filterAndReduceByProperty(string $property, $value): static
    {
        return parent::filterAndReduceByProperty($property, $value);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->merge() instead.
     *
     * @param EntityCollection<TElement> $collection
     */
    public function merge(EntityCollection $collection): void
    {
        parent::merge($collection);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->insert() instead.
     *
     * @param TElement $entity
     */
    public function insert(int $position, Entity $entity): void
    {
        parent::insert($position, $entity);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->getList() instead.
     *
     * @param array<string> $ids
     */
    public function getList(array $ids): static
    {
        return parent::getList($ids);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->sortByIdArray() instead.
     *
     * @param array<array-key, array<string>|string> $ids
     */
    public function sortByIdArray(array $ids): void
    {
        parent::sortByIdArray($ids);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->getCustomFieldsValues() instead.
     *
     * @return array<string, mixed>
     */
    public function getCustomFieldsValues(string ...$fields): array
    {
        return parent::getCustomFieldsValues(...$fields);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->getCustomFieldsValue() instead.
     *
     * @return array<string, mixed>
     */
    public function getCustomFieldsValue(string $field): array
    {
        return parent::getCustomFieldsValue($field);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed. Use getEntities()->setCustomFields() instead.
     *
     * @param array<string, array<string, mixed>> $values
     */
    public function setCustomFields(array $values): void
    {
        parent::setCustomFields($values);
    }

    /**
     * @deprecated tag:v6.8.0 reason:return-type-change - Will return EntityCollection instead of EntitySearchResult in v6.8.0.
     *
     * @param iterable<TElement> $elements
     *
     * @return static<TEntityCollection>
     */
    protected function createNew(iterable $elements = []): static
    {
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
