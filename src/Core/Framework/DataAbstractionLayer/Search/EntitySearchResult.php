<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\Feature;
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
class EntitySearchResult extends EntityCollection implements \JsonSerializable
{
    use StateAwareTrait;

    protected AggregationResultCollection $aggregations;

    protected int $page;

    protected ?int $limit = null;

    /**
     * @deprecated tag:v6.8.0 - The constructor signature will change in v6.8.0: the $entity parameter will be removed and the remaining parameters will reorder. See UPGRADE-6.8.md.
     *
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

        // Inline parent::__construct($entities) so we don't dispatch through our deprecated $this->set(). parent::set() bypasses dispatch and writes to $this->elements directly.
        foreach ($entities as $element) {
            parent::set($element->getUniqueIdentifier(), $element);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->filter() instead.
     */
    public function filter(\Closure $closure): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->filter()'));

        return $this->createNew($this->entities->filter($closure));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->slice() instead.
     */
    public function slice(int $offset, ?int $length = null): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->slice()'));

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
     * @deprecated tag:v6.8.0 - Will be removed; the result wrapper becomes immutable.
     */
    public function clear(): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'));

        parent::clear();

        $this->entities->clear();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed; the result wrapper becomes immutable.
     */
    public function add($entity): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'));

        parent::set($entity->getUniqueIdentifier(), $entity);

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
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'));

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
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'));

        $this->limit = $limit;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. The entity name is no longer exposed by the result wrapper.
     */
    public function getEntity(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'));

        return $this->entity;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. The entity name is no longer exposed by the result wrapper.
     */
    public function setEntity(string $entity): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'));

        $this->entity = $entity;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->getAt() instead.
     */
    public function getAt(int $position)
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->getAt()'));

        return $this->entities->getAt($position);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->fill() instead.
     */
    public function fill(array $entities): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->fill()'));

        // Inline parent::fill() so we don't dispatch through our deprecated $this->add(). Sync both $this->elements and $this->entities, like the original cascade did.
        foreach ($entities as $element) {
            parent::set($element->getUniqueIdentifier(), $element);
            $this->entities->add($element);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->set() instead.
     */
    public function set($key, $element): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->set()'));

        parent::set($key, $element);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->get() instead.
     */
    public function get($key)
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->get()'));

        return parent::get($key);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->count() or getTotal() instead.
     */
    public function count(): int
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->count() or getTotal()'));

        return parent::count();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->isEmpty() instead.
     */
    public function isEmpty(): bool
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->isEmpty()'));

        return parent::isEmpty();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->getKeys() instead.
     */
    public function getKeys(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->getKeys()'));

        return parent::getKeys();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->has() instead.
     */
    public function has($key): bool
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->has()'));

        return parent::has($key);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->map() instead.
     */
    public function map(\Closure $closure): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->map()'));

        return parent::map($closure);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->reduce() instead.
     */
    public function reduce(\Closure $closure, $initial = null)
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->reduce()'));

        return parent::reduce($closure, $initial);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->fmap() instead.
     */
    public function fmap(\Closure $closure): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->fmap()'));

        return parent::fmap($closure);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->flatMap() instead.
     */
    public function flatMap(\Closure $closure): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->flatMap()'));

        return parent::flatMap($closure);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->sort() instead.
     */
    public function sort(\Closure $closure): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->sort()'));

        parent::sort($closure);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->filterInstance() instead.
     */
    public function filterInstance(string $class): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->filterInstance()'));

        return parent::filterInstance($class);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->getElements() instead.
     */
    public function getElements(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->getElements()'));

        return parent::getElements();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->first() instead.
     */
    public function first()
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->first()'));

        return parent::first();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->firstWhere() instead.
     */
    public function firstWhere(\Closure $closure)
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->firstWhere()'));

        return parent::firstWhere($closure);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->last() instead.
     */
    public function last()
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->last()'));

        return parent::last();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->remove() instead.
     */
    public function remove($key): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->remove()'));

        parent::remove($key);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities() instead.
     */
    public function getIterator(): \Traversable
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()'));

        return parent::getIterator();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->assignRecursive() instead.
     */
    public function assignRecursive(array $options): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->assignRecursive()'));

        return parent::assignRecursive($options);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->getIds() instead.
     */
    public function getIds(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->getIds()'));

        return parent::getIds();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->filterByProperty() instead.
     */
    public function filterByProperty(string $property, $value): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->filterByProperty()'));

        return parent::filterByProperty($property, $value);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->filterAndReduceByProperty() instead.
     */
    public function filterAndReduceByProperty(string $property, $value): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->filterAndReduceByProperty()'));

        return parent::filterAndReduceByProperty($property, $value);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->merge() instead.
     */
    public function merge(EntityCollection $collection): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->merge()'));

        foreach ($collection as $entity) {
            if (parent::has($entity->getUniqueIdentifier())) {
                continue;
            }

            parent::set($entity->getUniqueIdentifier(), $entity);
            $this->entities->add($entity);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->insert() instead.
     */
    public function insert(int $position, Entity $entity): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->insert()'));

        parent::insert($position, $entity);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->getList() instead.
     */
    public function getList(array $ids): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->getList()'));

        return parent::getList($ids);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->sortByIdArray() instead.
     */
    public function sortByIdArray(array $ids): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->sortByIdArray()'));

        parent::sortByIdArray($ids);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->getCustomFieldsValues() instead.
     */
    public function getCustomFieldsValues(string ...$fields): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->getCustomFieldsValues()'));

        return parent::getCustomFieldsValues(...$fields);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->getCustomFieldsValue() instead.
     */
    public function getCustomFieldsValue(string $field): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->getCustomFieldsValue()'));

        return parent::getCustomFieldsValue($field);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use getEntities()->setCustomFields() instead.
     */
    public function setCustomFields(array $values): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getEntities()->setCustomFields()'));

        parent::setCustomFields($values);
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-getter-setter - Will be removed alongside filter() and slice().
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
