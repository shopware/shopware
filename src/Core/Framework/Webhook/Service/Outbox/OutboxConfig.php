<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service\Outbox;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
readonly class OutboxConfig
{
    /**
     * @internal
     */
    public function __construct(
        public int $maxRetries = 5,
        public int $baseDelaySeconds = 5,
        public int $delayMultiplier = 2,
        public int $batchSize = 10,
        public int $timeLimitSeconds = 20,
        public int $inlineRetryMinBackoffSeconds = 3,
        public int $maxWebhookErrorCount = 10,
        public int $requestTimeout = 20,
        public int $connectTimeout = 10,
    ) {
    }
}
