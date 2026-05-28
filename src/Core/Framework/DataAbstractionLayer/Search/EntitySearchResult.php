<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\StateAwareTrait;

/**
 * @final
 *
 * @deprecated tag:v6.8.0 - Will no longer extend EntityCollection.
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
    // Trait methods aliased to private so they can be re-exposed below with deprecation triggers (shopware.deprecatedClass).
    use StateAwareTrait {
        addState as private addStateFromTrait;
        removeState as private removeStateFromTrait;
        hasState as private hasStateFromTrait;
        getStates as private getStatesFromTrait;
        state as private stateFromTrait;
    }

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
     * @return static<TEntityCollection>
     */
    public function filter(\Closure $closure): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', '::getEntities()->filter()'));

        return $this->createNew($this->entities->filter($closure));
    }

    /**
     * @return static<TEntityCollection>
     */
    public function slice(int $offset, ?int $length = null): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', '::getEntities()->slice()'));

        return $this->createNew($this->entities->slice($offset, $length));
    }

    public function getTotal(): int
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->total;
    }

    /**
     * @return TEntityCollection
     */
    public function getEntities(): EntityCollection
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->entities;
    }

    public function getAggregations(): AggregationResultCollection
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->aggregations;
    }

    public function getCriteria(): Criteria
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->criteria;
    }

    public function getContext(): Context
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->context;
    }

    public function clear(): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', '::getEntities()->clear()'));

        parent::clear();

        $this->entities->clear();
    }

    public function add($entity): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', '::getEntities()->add()'));

        parent::add($entity);

        $this->entities->add($entity);
    }

    public function jsonSerialize(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        $vars = get_object_vars($this);

        unset($vars['criteria']);
        unset($vars['context']);
        unset($vars['entities']);

        $this->convertDateTimePropertiesToJsonStringRepresentation($vars);

        return $vars;
    }

    public function getApiAlias(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return 'dal_entity_search_result';
    }

    public function getPage(): int
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->page;
    }

    public function setPage(int $page): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        $this->page = $page;
    }

    public function getLimit(): ?int
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->limit;
    }

    public function setLimit(int $limit): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        $this->limit = $limit;
    }

    public function getEntity(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->entity;
    }

    public function setEntity(string $entity): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        $this->entity = $entity;
    }

    /**
     * @return TElement|null
     */
    public function getAt(int $position)
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', '::getEntities()->getAt()'));

        return $this->entities->getAt($position);
    }

    public function addState(string ...$states): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        $this->addStateFromTrait(...$states);
    }

    public function removeState(string $state): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        $this->removeStateFromTrait($state);
    }

    public function hasState(string ...$states): bool
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->hasStateFromTrait(...$states);
    }

    /**
     * @return array<string>
     */
    public function getStates(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->getStatesFromTrait();
    }

    public function state(\Closure $closure, string ...$states): mixed
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->stateFromTrait($closure, ...$states);
    }

    /**
     * @param iterable<TElement> $elements
     *
     * @return static<TEntityCollection>
     */
    protected function createNew(iterable $elements = []): static
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'));

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
