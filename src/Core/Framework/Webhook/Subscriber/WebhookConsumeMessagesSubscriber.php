<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Subscriber;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Active only under WEBHOOKS_REWORK on 6.7. With the flag off, WebhookTransport forwards
 * to `async`, so existing `messenger:consume async` workers already drain webhooks and no
 * widening is needed. With the flag on, MySQLWebhookReceiver becomes the consumer and an
 * `async`-only command would silently miss deliveries — this bridge prepends `webhook` to
 * any default-async command so legacy deployments keep working through the rollout.
 * In v6.8 the runtime mutation is removed and operators must list `webhook` explicitly.
 *
 * @internal
 *
 * @deprecated tag:v6.8.0 - reason:remove-subscriber - Define the webhook transport in the
 *     Messenger configuration and add it to your consume command explicitly.
 */
#[Package('framework')]
class WebhookConsumeMessagesSubscriber implements EventSubscriberInterface
{
    public const WEBHOOK_QUEUE = 'webhook';
    private const DEFAULT_ASYNC_QUEUE = 'async';

    public static function getSubscribedEvents(): array
    {
        return [ConsoleEvents::COMMAND => 'onMessengerConsume'];
    }

    public function onMessengerConsume(ConsoleCommandEvent $event): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            return;
        }

        // Under flag-off, WebhookTransport forwards to async — existing `messenger:consume async`
        // workers already drain webhooks, no widening needed.
        if (!Feature::isActive('WEBHOOKS_REWORK')) {
            return;
        }

        if ($event->getCommand()?->getName() !== 'messenger:consume') {
            return;
        }

        /** @var list<string> $receivers */
        $receivers = $event->getInput()->getArgument('receivers');
        if (!\in_array(self::DEFAULT_ASYNC_QUEUE, $receivers, true)) {
            return;
        }
        if (\in_array(self::WEBHOOK_QUEUE, $receivers, true)) {
            return;
        }

        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        // First so `async` doesn't starve it; the fixed per-poll batch size provides
        // implicit fairness to other transports.
        array_unshift($receivers, self::WEBHOOK_QUEUE);
        $event->getInput()->setArgument('receivers', $receivers);
    }
}
