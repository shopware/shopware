<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Event\MaintenanceModeRequestEvent;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('core')]
class MaintenanceModeResolver
{
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    /**
     * @param string[] $allowedIps
     */
    public function isClientAllowed(Request $request, array $allowedIps): bool
    {
        $isAllowed = IpUtils::checkIp(
            (string) $request->getClientIp(),
            $allowedIps
        );

        $event = new MaintenanceModeRequestEvent($request, $allowedIps, $isAllowed);

        $this->eventDispatcher->dispatch($event);

        return $event->isClientAllowed();
    }
}
