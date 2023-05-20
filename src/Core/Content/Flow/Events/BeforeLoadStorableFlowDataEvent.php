<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Events;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('business-ops')]
class BeforeLoadStorableFlowDataEvent extends Event implements ShopwareEvent, GenericEvent
{
    public function __construct(
        private readonly string $entityName,
        private readonly Criteria $criteria,
        private readonly Context $context,
    ) {
    }

    public function getName(): string
    {
        return 'flow.storer.' . $this->entityName . '.criteria.event';
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
