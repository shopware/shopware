<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Outbox;

use Shopware\Core\Framework\Log\Package;

/**
 * State of an outbox entry after it has been claimed for processing.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class OutboxEntry
{
    public function __construct(
        /**
         * Total attempts made so far (1 = first attempt, 2 = first retry, etc.)
         */
        public int $executionCount,
        /**
         * webhook_delivery.id — monotonic sequence number
         */
        public int $sequence,
    ) {
    }
}
