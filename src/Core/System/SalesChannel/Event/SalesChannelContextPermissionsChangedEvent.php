<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelContextPermissionsChangedEvent extends NestedEvent
{
    /**
     * @var array
     */
    protected $permissions = [];

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    public function __construct(SalesChannelContext $context, array $permissions)
    {
        $this->salesChannelContext = $context;
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

    public function getPermissions(): array
    {
        return $this->permissions;
    }
}
