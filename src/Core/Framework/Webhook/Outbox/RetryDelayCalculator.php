<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Outbox;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * Pure delay calculator: computes the next retry time from a fixed delay lookup table.
 * Does not decide whether to retry — that decision belongs in the orchestrator.
 *
 * @internal
 */
#[Package('framework')]
class RetryDelayCalculator
{
    /**
     * Fixed retry delay lookup table: 5s, 30s, 5min, 30min, 4h.
     *
     * @var list<int>
     */
    public const RETRY_DELAYS = [5, 30, 300, 1800, 14400];

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Computes the next retry time based on how many attempts have been made.
     * Uses the last delay value for execution counts beyond the table size.
     * Defensively clamps zero/negative counts to the first delay.
     */
    public function computeNextRetryAt(int $executionCount): \DateTimeImmutable
    {
        $delayIndex = min(max($executionCount - 1, 0), \count(self::RETRY_DELAYS) - 1);
        $delaySeconds = self::RETRY_DELAYS[$delayIndex];

        return $this->clock->now()->modify(\sprintf('+%s seconds', $delaySeconds));
    }
}
