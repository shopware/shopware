<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 *
 * @template TEntityCollection of EntityCollection
 */
#[Package('framework')]
class EntitySearchResult
{
    protected int $page;

    protected ?int $limit = null;

    /**
     * @param TEntityCollection $entities
     */
    final public function __construct(
        protected int $total,
        protected EntityCollection $entities,
        protected Criteria $criteria,
        protected Context $context,
        protected AggregationResultCollection $aggregations = new AggregationResultCollection(),
    ) {
        $this->limit = $criteria->getLimit();
        $this->page = !$criteria->getLimit() ? 1 : (int) ceil((($criteria->getOffset() ?? 0) + 1) / $criteria->getLimit());
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

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function jsonSerialize(): array
    {
        $vars = get_object_vars($this);

        unset($vars['criteria']);
        unset($vars['context']);
        unset($vars['entities']);

        return $vars;
    }

    public function getApiAlias(): string
    {
        return 'dal_entity_search_result';
    }
}
