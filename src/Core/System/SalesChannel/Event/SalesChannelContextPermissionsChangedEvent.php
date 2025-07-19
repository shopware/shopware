<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
class SalesChannelContextPermissionsChangedEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    /**
     * @param array<string, bool> $permissions
     */
    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
        protected array $permissions = []
    ) {
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    /**
     * @return array<string, bool>
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }
}
