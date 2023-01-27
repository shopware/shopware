<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('sales-channel')]
class SalesChannelEntityIdSearchResultLoadedEvent extends EntityIdSearchResultLoadedEvent implements ShopwareSalesChannelEvent
{
    public function __construct(
        EntityDefinition $definition,
        IdSearchResult $result,
        private readonly SalesChannelContext $salesChannelContext
    ) {
        parent::__construct($definition, $result);
    }

    public function getName(): string
    {
        return 'sales_channel.' . parent::getName();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
