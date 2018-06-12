<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\Struct\ArrayStruct;

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
     * @var AggregatorResult|null
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
     * @var IdSearchResult
     */
    protected $idSearchResult;

    public function __construct(
        IdSearchResult $idSearchResult,
        EntityCollection $entities,
        ?AggregatorResult $aggregations,
        Criteria $criteria,
        Context $context
    ) {
        parent::__construct($entities->getElements());
        $this->entities = $entities;
        $this->total = $idSearchResult->getTotal();
        $this->aggregations = $aggregations;
        $this->idSearchResult = $idSearchResult;
        $this->criteria = $criteria;
        $this->context = $context;

        $search = $this->idSearchResult->getData();

        /** @var Entity $element */
        foreach ($this->entities->getElements() as $element) {
            if (!array_key_exists($element->getId(), $search)) {
                continue;
            }
            $data = $search[$element->getId()];

            $element->addExtension('search', new ArrayStruct($data));
        }
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getEntities(): EntityCollection
    {
        return $this->entities;
    }

    public function getAggregations(): ?AggregatorResult
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

    public function getIdSearchResult(): IdSearchResult
    {
        return $this->idSearchResult;
    }
}
