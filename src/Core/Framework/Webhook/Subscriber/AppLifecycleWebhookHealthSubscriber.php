<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Subscriber;

use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Event\ManifestChangedEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * An app install or update is a clean slate for its webhooks' health; time spent deactivated must
 * not count toward the suspension bound.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Webhook\Subscriber\ReactivateWebhooksOnAppReregistrationSubscriberTest
 * @see \Shopware\Tests\Integration\Core\Framework\Webhook\Health\WebhookHealthTickTest
 */
#[Package('framework')]
class AppLifecycleWebhookHealthSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly WebhookHealthService $healthService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppInstalledEvent::class => 'reactivate',
            AppUpdatedEvent::class => 'reactivate',
            AppDeactivatedEvent::class => 'pauseSuspensionClock',
            AppActivatedEvent::class => 'resumeSuspensionClock',
        ];
    }

    public function reactivate(ManifestChangedEvent $event): void
    {
        if (!Feature::isActive('WEBHOOKS_REWORK')) {
            return;
        }

        $this->healthService->reactivateForApp($event->getApp()->getId());
    }

    public function pauseSuspensionClock(AppDeactivatedEvent $event): void
    {
        if (!Feature::isActive('WEBHOOKS_REWORK')) {
            return;
        }

        $this->healthService->pauseSuspensionClockForApp($event->getApp()->getId());
    }

    public function resumeSuspensionClock(AppActivatedEvent $event): void
    {
        if (!Feature::isActive('WEBHOOKS_REWORK')) {
            return;
        }

        $this->healthService->resumeSuspensionClockForApp($event->getApp()->getId());
    }
}
