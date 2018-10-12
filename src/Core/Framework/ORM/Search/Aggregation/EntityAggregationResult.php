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

    public function __construct(Aggregation $aggregation, string $definition, EntityCollection $entities)
    {
        parent::__construct($aggregation);

        $this->definition = $definition;
        $this->entities = $entities;
    }

    public function getDefinition(): string
    {
        return $this->definition;
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
