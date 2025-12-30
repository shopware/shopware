<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopware\Core\Framework\MessageQueue\DeduplicatableMessageInterface;

/**
 * Signal message to trigger draining the webhook outbox.
 *
 * @internal
 */
#[Package('framework')]
final class WebhookOutboxSignalMessage implements AsyncMessageInterface, DeduplicatableMessageInterface
{
    /**
     * @param int $depth Used to prevent infinite loops or excessive chaining of signal messages.
     *                   When the outbox has more work, we re-dispatch this message with depth + 1.
     *                   If depth reaches a threshold, we stop re-dispatching to yield to other messages.
     */
    public function __construct(
        private readonly int $depth = 0,
    ) {
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function deduplicationId(): string
    {
        return 'webhook-outbox-signal';
    }
}
