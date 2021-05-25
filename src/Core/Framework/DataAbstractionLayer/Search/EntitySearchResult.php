<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;

class EntitySearchResult extends EntityCollection
{
    /**
     * @var string
     */
    protected $entity;

    /**
     * @var int
     */
    protected $total;

    /**
     * @var EntityCollection
     */
    protected $entities;

    /**
     * @var AggregationResultCollection
     */
    protected $aggregations;

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

    final public function __construct(
        string $entity,
        int $total,
        EntityCollection $entities,
        ?AggregationResultCollection $aggregations,
        Criteria $criteria,
        Context $context
    ) {
        $this->entities = $entities;
        $this->total = $total;
        $this->aggregations = $aggregations ?? new AggregationResultCollection();
        $this->criteria = $criteria;
        $this->context = $context;
        $this->limit = $criteria->getLimit();
        $this->page = !$criteria->getLimit() ? 1 : (int) ceil(($criteria->getOffset() ?? 0 + 1) / $criteria->getLimit());

        parent::__construct($entities);
        $this->entity = $entity;
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
     * @param EntityCollection $elements
     */
    protected function createNew(iterable $elements = [])
    {
        return new static(
            $this->entity,
            $this->total,
            $elements,
            $this->aggregations,
            $this->criteria,
            $this->context
        );
    }
}
