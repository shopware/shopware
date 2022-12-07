<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;

/**
 * @final
 */
class EntityAggregation extends Aggregation
{
    public function __construct(string $name, string $field, private string $entity)
    {
        parent::__construct($name, $field);
    }

    public function getEntity(): string
    {
        return $this->entity;
    }
}
