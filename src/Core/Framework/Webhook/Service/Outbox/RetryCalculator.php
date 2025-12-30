<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service\Outbox;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class RetryCalculator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly OutboxConfig $config,
        private readonly ClockInterface $clock
    ) {
    }

    public function calculateNextRetry(int $currentExecutionCount): ?\DateTimeImmutable
    {
        if ($currentExecutionCount >= $this->config->maxRetries) {
            return null;
        }

        $delaySeconds = (int) ($this->config->baseDelaySeconds * ($this->config->delayMultiplier ** $currentExecutionCount));

        return $this->clock->now()->modify(sprintf('+%d seconds', $delaySeconds));
    }
}
