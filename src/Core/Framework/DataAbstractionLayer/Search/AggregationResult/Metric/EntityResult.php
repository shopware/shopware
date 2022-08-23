<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;

/**
 * @final tag:v6.5.0
 */
class EntityResult extends AggregationResult
{
    /**
     * @var EntityCollection<Entity>
     */
    protected $entities;

    /**
     * @param EntityCollection<Entity> $entities
     */
    public function __construct(string $name, EntityCollection $entities)
    {
        parent::__construct($name);
        $this->entities = $entities;
    }

    /**
     * @return EntityCollection<Entity>
     */
    public function getEntities(): EntityCollection
    {
        return $this->entities;
    }

    public function add(Entity $entity): void
    {
        $this->entities->add($entity);
    }
}
