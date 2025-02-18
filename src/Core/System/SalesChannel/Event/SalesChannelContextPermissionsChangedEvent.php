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
     * @var array<string, bool>
     */
    protected array $permissions = [];

    /**
     * @param array<string, bool> $permissions
     */
    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
        array $permissions
    ) {
        $this->permissions = $permissions;
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
