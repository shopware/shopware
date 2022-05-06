<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-final - Will be @final
 * @final
 */
class EntityAggregation extends Aggregation
{
    /**
     * @var string
     */
    private $entity;

    public function __construct(string $name, string $field, string $entity)
    {
        parent::__construct($name, $field);
        $this->entity = $entity;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }
}
