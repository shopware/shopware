<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Subscriber;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Prepends the `webhook` receiver to every `messenger:consume` invocation during the
 * WEBHOOKS_REWORK rollout so operators don't need to update their existing consume
 * command. Webhook goes first; fairness with other receivers relies on natural
 * partition drain between `get()` batches.
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

        // `messenger:consume --queues=X` requires every receiver to implement
        // QueueReceiverInterface (Symfony Worker::run). WebhookTransport does not. Prepending
        // it would crash the worker at startup with a RuntimeException, so leave commands that
        // opt into queue filtering alone.
        if ($event->getInput()->getOption('queues') !== []) {
            return;
        }

        /** @var list<string> $receivers */
        $receivers = $event->getInput()->getArgument('receivers');
        if ($receivers === [] || \in_array(self::WEBHOOK_QUEUE, $receivers, true)) {
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
