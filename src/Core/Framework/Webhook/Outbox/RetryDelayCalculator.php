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

    /**
     * @var positive-int
     */
    private const RETRY_AFTER_MIN_SECONDS = 1;

    /**
     * @var positive-int
     */
    private const RETRY_AFTER_MAX_SECONDS = 14400;

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    public function computeNextRetryAt(int $executionCount, ?string $retryAfter = null): \DateTimeImmutable
    {
        $now = $this->clock->now();

        $retryAfterSeconds = $this->retryAfterSeconds($retryAfter, $now);
        if ($retryAfterSeconds !== null) {
            return $now->modify(\sprintf('+%d seconds', $retryAfterSeconds));
        }

        $delayIndex = min(max($executionCount - 1, 0), \count(self::RETRY_DELAYS_IN_SECONDS) - 1);

        return $now->modify(\sprintf('+%d seconds', self::RETRY_DELAYS_IN_SECONDS[$delayIndex]));
    }

    private function retryAfterSeconds(?string $retryAfter, \DateTimeImmutable $now): ?int
    {
        if ($retryAfter === null) {
            return null;
        }

        $value = trim($retryAfter);
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            $seconds = (int) $value;
        } else {
            // RFC7231's GMT suffix is literal, so pin parsing to UTC.
            $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC7231, $value, new \DateTimeZone('UTC'));
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                return null;
            }

            $seconds = $date->getTimestamp() - $now->getTimestamp();
        }

        if ($seconds < self::RETRY_AFTER_MIN_SECONDS || $seconds > self::RETRY_AFTER_MAX_SECONDS) {
            return null;
        }

        return $seconds;
    }
}
