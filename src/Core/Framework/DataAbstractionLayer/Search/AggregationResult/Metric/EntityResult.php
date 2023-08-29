<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 *
 * @template TEntityCollection of EntityCollection
 */
#[Package('core')]
class EntityResult extends AggregationResult
{
    /**
     * @param TEntityCollection $entities
     */
    public function __construct(string $name, protected EntityCollection $entities)
    {
        parent::__construct($name);
    }

    /**
     * @return TEntityCollection
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
