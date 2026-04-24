<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Subscriber;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Prepends the `webhook` receiver in front of `async` so operators running the default
 * `messenger:consume async` pick up webhook deliveries without changing their command.
 * Only fires when `async` is explicitly requested — specialty workers (e.g.
 * `messenger:consume failed` or a custom priority queue) are left untouched so webhooks
 * don't silently ride along into a scope they weren't meant for.
 *
 * Operators running `messenger:consume async --queues=X` will see Symfony's own
 * RuntimeException at worker startup because WebhookTransport does not implement
 * QueueReceiverInterface — by design. That deployment needs a dedicated
 * `messenger:consume webhook` command anyway (the webhook transport stops forwarding
 * to async under the flag), so the crash is the right signal.
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
