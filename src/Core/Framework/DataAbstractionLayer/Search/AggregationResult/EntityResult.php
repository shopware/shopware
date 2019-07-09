<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class EntityResult extends AbstractAggregationResult
{
    /**
     * @var EntityCollection
     */
    protected $entities;

    public function __construct(?array $key, EntityCollection $entities)
    {
        parent::__construct($key);
        $this->entities = $entities;
    }

    public function getEntities(): EntityCollection
    {
        return $this->entities;
    }

    public function add(Entity $entity): void
    {
        $this->entities->add($entity);
    }
}
