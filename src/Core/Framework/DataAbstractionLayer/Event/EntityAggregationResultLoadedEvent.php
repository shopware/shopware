<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregatorResult;
use Shopware\Core\Framework\Event\NestedEvent;

class EntityAggregationResultLoadedEvent extends NestedEvent
{
    /**
     * @var AggregatorResult
     */
    protected $result;

    /**
     * @var string|EntityDefinition
     */
    protected $definition;

    /**
     * @var string
     */
    protected $name;

    /** @param string|EntityDefinition $definition */
    public function __construct(string $definition, AggregatorResult $result)
    {
        $this->result = $result;
        $this->definition = $definition;
        $this->name = $this->definition::getEntityName() . '.aggregation.result.loaded';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }

    public function getResult(): AggregatorResult
    {
        return $this->result;
    }
}
