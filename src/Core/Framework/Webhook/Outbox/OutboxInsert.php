<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Outbox;

use Shopware\Core\Framework\Log\Package;

/**
 * Data for inserting a new outbox entry (webhook_event_log + webhook_delivery).
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class OutboxInsert
{
    public function __construct(
        public string $webhookEventId,
        public string $webhookId,
        public string $partitionKey,
        public string $serializedMessage,
    ) {
    }
}
