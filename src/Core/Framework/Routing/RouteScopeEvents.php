<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

class RouteScopeEvents
{
    /**
     * @Event("Shopware\Core\Framework\Routing\Event\RouteScopeWhitlistCollectEvent")
     */
    public const ROUTE_SCOPE_WHITELIST_COLLECT = 'route_scope.whitelist.collect';
}
