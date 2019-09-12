<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;

class EntityResult extends AggregationResult
{
    /**
     * @var EntityCollection
     */
    protected $entities;

    public function __construct(string $name, EntityCollection $entities)
    {
        parent::__construct($name);
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
