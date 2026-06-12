<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Subscriber;

use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\EndpointLifecycle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A deactivated app's events are filtered out before they reach the dispatch gate, so its
 * SUSPENDED webhooks get no recovery trials. Counting that time toward the 7-day DISABLED
 * limit would retire them without ever giving them a chance. This subscriber marks the
 * pause start; the health task then keeps shifting `suspended_since` forward while the app
 * stays deactivated, so only time with a live recovery path counts (ADR §SUSPENDED).
 *
 * @internal
 */
#[Package('framework')]
class PauseSuspensionClockOnAppDeactivationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EndpointLifecycle $endpointLifecycle,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppDeactivatedEvent::class => 'onAppDeactivated',
        ];
    }

    public function onAppDeactivated(AppDeactivatedEvent $event): void
    {
        if (!Feature::isActive('WEBHOOKS_REWORK')) {
            return;
        }

        $this->endpointLifecycle->pauseSuspensionClockForApp($event->getApp()->getId());
    }
}
