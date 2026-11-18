<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Event\MaintenanceModeRequestEvent;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Allows clients whose session holds a valid maintenance password grant, so every maintenance decision
 * (routing, HTTP cache) treats them like an allowlisted client.
 *
 * @internal
 */
#[Package('discovery')]
class SalesChannelMaintenancePasswordAccessSubscriber implements EventSubscriberInterface
{
    final public const SESSION_KEY_PREFIX = 'maintenance_access_';

    private const ATTRIBUTE_GRANTED = '_sw_maintenance_password_granted';

    /**
     * @return array<string, string|array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MaintenanceModeRequestEvent::class => 'allowGrantedClient',
            // must run before the session listener saves the session, the HTTP cache decision comes after that
            KernelEvents::RESPONSE => ['rememberGrant', 0],
        ];
    }

    public function allowGrantedClient(MaintenanceModeRequestEvent $event): void
    {
        if (!$event->isClientAllowed() && $this->isGranted($event->getRequest())) {
            $event->allowClient();
        }
    }

    public function rememberGrant(ResponseEvent $event): void
    {
        $this->isGranted($event->getRequest());
    }

    private function isGranted(Request $request): bool
    {
        if ($request->attributes->has(self::ATTRIBUTE_GRANTED)) {
            return $request->attributes->getBoolean(self::ATTRIBUTE_GRANTED);
        }

        $salesChannelId = (string) $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, '');
        $passwordHash = (string) $request->attributes->get(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_PASSWORD, '');

        // @phpstan-ignore shopware.unsafeRequestHasSession (The active maintenance gate must load the session grant.)
        if ($salesChannelId === '' || $passwordHash === '' || !$request->attributes->getBoolean(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE) || !$request->hasSession()) {
            return false;
        }

        $passwordGeneration = (string) $request->attributes->get(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_PASSWORD_GENERATION, '');
        $grantedHash = $request->getSession()->get(self::SESSION_KEY_PREFIX . $salesChannelId);
        $granted = \is_string($grantedHash) && hash_equals($passwordHash . ':' . $passwordGeneration, $grantedHash);

        $request->attributes->set(self::ATTRIBUTE_GRANTED, $granted);

        return $granted;
    }
}
