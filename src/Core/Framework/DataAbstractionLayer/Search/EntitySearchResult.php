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
 * @extends EntityCollection<Entity>
 */
#[Package('core')]
class EntitySearchResult extends EntityCollection
{
    use StateAwareTrait;

    protected AggregationResultCollection $aggregations;

    /**
     * @var Criteria
     */
    protected $criteria;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var int
     */
    protected $page;

    /**
     * @var int|null
     */
    protected $limit;

    /**
     * @phpstan-ignore-next-line -> we can't generalize the type of EntityCollection here
     */
    final public function __construct(
        protected string $entity,
        protected int $total,
        protected EntityCollection $entities,
        ?AggregationResultCollection $aggregations,
        Criteria $criteria,
        Context $context
    ) {
        $this->criteria = $criteria;
        $this->context = $context;
        $this->aggregations = $aggregations ?? new AggregationResultCollection();
        $this->limit = $criteria->getLimit();
        $this->page = !$criteria->getLimit() ? 1 : (int) ceil((($criteria->getOffset() ?? 0) + 1) / $criteria->getLimit());

        parent::__construct($entities);
    }

    public function filter(\Closure $closure)
    {
        return $this->createNew($this->entities->filter($closure));
    }

    public function slice(int $offset, ?int $length = null)
    {
        return $this->createNew($this->entities->slice($offset, $length));
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @return EntityCollection<Entity>
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

    public function clear(): void
    {
        parent::clear();

        $this->entities->clear();
    }

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

    public function getPage(): ?int
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

    public function setEntity(string $entity): void
    {
        $this->entity = $entity;
    }

    /**
     * @return Entity|null
     */
    public function getAt(int $position)
    {
        return $this->entities->getAt($position);
    }

    /**
     * @return static
     *
     * @deprecated tag:v6.6.0  - reason:return-type-change - Return type will be changed to `static`
     */
    protected function createNew(iterable $elements = [])
    {
        if (!($elements instanceof EntityCollection)) {
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
