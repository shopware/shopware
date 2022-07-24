<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class SalesChannelRepositoryProcessCriteriaEvent extends Event implements ShopwareSalesChannelEvent, GenericEvent
{
    private EntityDefinition $definition;

    private Criteria $criteria;

    private SalesChannelContext $salesChannelContext;

    public function __construct(EntityDefinition $definition, Criteria $criteria, SalesChannelContext $salesChannelContext)
    {
        $this->definition = $definition;
        $this->criteria = $criteria;
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getName(): string
    {
        return "sales_channel.{$this->definition->getEntityName()}.process_criteria";
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }
}
