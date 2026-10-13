<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\App\AppEvents;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Event\WebhookActivatedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookDegradedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookDisabledEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookSuspendedEvent;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('framework')]
class WebhookCacheClearer implements EventSubscriberInterface, ResetInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly WebhookManager $manager)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppEvents::APP_WRITTEN_EVENT => 'clearWebhookCache',
            // Health events are also dispatched from long-running webhook workers, whose cached subscriber list can
            // predate webhooks other processes created; the listeners run before the webhook fan-out.
            WebhookActivatedEvent::class => 'clearWebhookCache',
            WebhookDegradedEvent::class => 'clearWebhookCache',
            WebhookSuspendedEvent::class => 'clearWebhookCache',
            WebhookDisabledEvent::class => 'clearWebhookCache',
        ];
    }

    /**
     * Reset can not be handled by the Dispatcher itself, as it may be in the middle of a decoration chain
     * Therefore tagging that service directly won't work
     */
    public function reset(): void
    {
        $this->clearWebhookCache();
    }

    public function clearWebhookCache(): void
    {
        $this->manager->clearInternalWebhookCache();
    }
}
