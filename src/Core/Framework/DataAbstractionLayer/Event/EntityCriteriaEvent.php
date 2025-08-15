<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

class EntityCriteriaEvent extends Event implements ShopwareEvent, GenericEvent
{
    public function __construct(
        private readonly EntityDefinition $definition,
        private readonly Criteria $criteria,
        private readonly Context $context
    ) {
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getEntityName(): string
    {
        return $this->definition->getEntityName();
    }

    public function getEventName(): string
    {
        return $this->definition->getEntityName() . '.criteria';
    }
}
