<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Event;

use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Routing\RouteScopeEvents;
use Symfony\Contracts\EventDispatcher\Event;

class RouteScopeWhitlistCollectEvent extends Event implements GenericEvent
{
    public const NAME = RouteScopeEvents::ROUTE_SCOPE_WHITELIST_COLLECT;

    /**
     * @var array
     */
    private $whitelistedControllers;

    public function __construct(array $whitelistedControllers)
    {
        $this->whitelistedControllers = $whitelistedControllers;
    }

    public function getName(): string
    {
        return RouteScopeEvents::ROUTE_SCOPE_WHITELIST_COLLECT;
    }

    public function getWhitelistedControllers(): array
    {
        return $this->whitelistedControllers;
    }

    public function setWhitelistedControllers(array $whitelistedControllers): void
    {
        $this->whitelistedControllers = $whitelistedControllers;
    }
}
