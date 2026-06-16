<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Outbox;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class StreamLease
{
    public function __construct(
        public string $partitionKey,
        public string $workerId,
        public \DateTimeImmutable $acquiredAt,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
