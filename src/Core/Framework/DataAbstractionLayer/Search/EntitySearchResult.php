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

    final public function __construct(
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

    protected function createNew(iterable $elements = [])
    {
        return new static(
            $this->total,
            $elements,
            $this->aggregations,
            $this->criteria,
            $this->context
        );
    }
}
