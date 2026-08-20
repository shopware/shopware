<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Subscriber;

use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Time spent with the app deactivated must not count toward the suspension bound.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Webhook\Health\WebhookHealthTickTest
 */
#[Package('framework')]
class AppSuspensionClockSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly WebhookHealthService $healthService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppDeactivatedEvent::class => 'onAppDeactivated',
            AppActivatedEvent::class => 'onAppActivated',
        ];
    }

    public function onAppDeactivated(AppDeactivatedEvent $event): void
    {
        if (!Feature::isActive('WEBHOOKS_REWORK')) {
            return;
        }

        $this->healthService->pauseSuspensionClockForApp($event->getApp()->getId());
    }

    public function onAppActivated(AppActivatedEvent $event): void
    {
        if (!Feature::isActive('WEBHOOKS_REWORK')) {
            return;
        }

        $this->healthService->resumeSuspensionClockForApp($event->getApp()->getId());
    }
}
