<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;

class EntitySearchResult extends EntityCollection
{
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
        int $total,
        EntityCollection $entities,
        ?AggregationResultCollection $aggregations,
        Criteria $criteria,
        Context $context,
        int $page = 1,
        ?int $limit = null
    ) {
        $this->entities = $entities;
        $this->total = $total;
        $this->aggregations = $aggregations ?? new AggregationResultCollection();
        $this->criteria = $criteria;
        $this->context = $context;
        $this->page = $page;
        $this->limit = $limit;

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

    protected function createNew(iterable $elements = [])
    {
        return new static(
            $this->total,
            $elements,
            $this->aggregations,
            $this->criteria,
            $this->context,
            $this->page,
            $this->limit
        );
    }
}
