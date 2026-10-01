<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Outbox;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class RetryDelayCalculator
{
    /**
     * @var list<int>
     */
    public const RETRY_DELAYS_IN_SECONDS = [5, 30, 300, 1800, 14400];

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    public function computeNextRetryAt(int $executionCount): \DateTimeImmutable
    {
        $delayIndex = min(max($executionCount - 1, 0), \count(self::RETRY_DELAYS_IN_SECONDS) - 1);
        $delaySeconds = self::RETRY_DELAYS_IN_SECONDS[$delayIndex];

        return $this->clock->now()->modify(\sprintf('+%s seconds', $delaySeconds));
    }
}
