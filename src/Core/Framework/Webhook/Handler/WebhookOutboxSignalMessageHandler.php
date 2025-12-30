<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Handler;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Message\WebhookOutboxSignalMessage;
use Shopware\Core\Framework\Webhook\Service\WebhookOutboxProcessor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handles the WebhookOutboxSignalMessage by draining the outbox and rescheduling if needed.
 *
 * The signal chain mechanism ensures continuous processing of the webhook outbox as long as there is work to be done,
 * while preventing deep recursion and allowing other messages to be processed in between.
 * - If work was done and more remains, dispatch a new signal immediately
 * - If no work could be claimed, let the signal chain die (new webhooks trigger new signals)
 *
 * @internal
 */
#[AsMessageHandler]
#[Package('framework')]
final readonly class WebhookOutboxSignalMessageHandler
{
    /**
     * Maximum depth before resetting to 0 to prevent potential stack issues
     * and allow the message to be processed by a fresh worker.
     */
    private const MAX_SIGNAL_CHAIN = 10;

    public function __construct(
        private WebhookOutboxProcessor $processor,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(WebhookOutboxSignalMessage $message): void
    {
        $workerId = Uuid::randomHex();

        $hasMore = $this->processor->drain($workerId);

        if ($hasMore) {
            $this->dispatchNextSignal($message);
        }
    }

    private function dispatchNextSignal(WebhookOutboxSignalMessage $message): void
    {
        $nextDepth = $message->getDepth() + 1;
        if ($nextDepth >= self::MAX_SIGNAL_CHAIN) {
            // don't exceed max depth, yield to other messages. The drain task will be picked up again later.
            return;
        }

        $this->bus->dispatch(new WebhookOutboxSignalMessage($nextDepth));
    }
}
