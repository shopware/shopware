<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Subscriber;

use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Event\ManifestChangedEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\EndpointLifecycle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resets an app's webhook health to HEALTHY on an app install/update. A config change is a manual
 * operator action — a clean slate — so it clears the app's non-HEALTHY webhooks (the recovery path
 * for auth-suspended endpoints, which are never auto-probed). The reset is a no-op when none of the
 * app's webhooks are non-HEALTHY. A bare secret rotation dispatches no event, so it is not covered.
 *
 * @internal
 */
#[Package('framework')]
class ReactivateWebhooksOnAppReregistrationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ?EndpointLifecycle $endpointLifecycle = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppInstalledEvent::class => 'reactivate',
            AppUpdatedEvent::class => 'reactivate',
        ];
    }

    public function reactivate(ManifestChangedEvent $event): void
    {
        if (!Feature::isActive('WEBHOOKS_REWORK')) {
            return;
        }

        $this->endpointLifecycle?->reactivateForApp($event->getApp()->getId());
    }
}
