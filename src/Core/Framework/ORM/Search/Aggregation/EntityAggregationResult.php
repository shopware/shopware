<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search\Aggregation;

use Shopware\Core\Framework\ORM\EntityCollection;

class EntityAggregationResult extends AggregationResult
{
    /**
     * @var string
     */
    protected $definition;

    /**
     * @var EntityCollection
     */
    protected $entities;

    public function __construct(EntityAggregation $aggregation, EntityCollection $entities)
    {
        parent::__construct($aggregation);

        $this->entities = $entities;
    }

    public function getDefinition(): string
    {
        /** @var EntityAggregation $entityAggregation */
        $entityAggregation = $this->getAggregation();

        return $entityAggregation->getDefinition();
    }

    public function getEntities(): EntityCollection
    {
        return $this->entities;
    }

    public function getResult(): array
    {
        return $this->entities->getElements();
    }
}
