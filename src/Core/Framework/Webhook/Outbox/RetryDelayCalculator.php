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
     * A 429's `Retry-After` header is honoured only within these bounds. 4 h is the ceiling — the
     * same as the fixed schedule's own top tier. Out-of-range or malformed values fall back to the
     * fixed schedule.
     */
    private const RETRY_AFTER_MIN_SECONDS = 1;
    private const RETRY_AFTER_MAX_SECONDS = 14400;

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * `$retryAfter` is the raw value of a 429 response's `Retry-After` header (delta-seconds or an
     * HTTP-date), pulled out at the failure call site. If it parses to a delay between 1 s and
     * 4 h, it wins. Anything missing, malformed, in the past, or out of range falls back to the
     * fixed backoff schedule.
     */
    public function computeNextRetryAt(int $executionCount, ?string $retryAfter = null): \DateTimeImmutable
    {
        $now = $this->clock->now();

        $honoured = $this->retryAfterSeconds($retryAfter, $now);
        if ($honoured !== null) {
            return $now->modify(\sprintf('+%d seconds', $honoured));
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
            // An HTTP-date is always GMT. In RFC7231's format string, `\G\M\T` is a literal, not a
            // timezone token, so the parse must be pinned to UTC explicitly — otherwise it would
            // inherit the process default timezone.
            $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC7231, $value, new \DateTimeZone('UTC'));
            $errors = \DateTimeImmutable::getLastErrors();
            // Reject hard parse failures AND inputs that are malformed but silently normalised
            // (wrong weekday, overflowed fields) — createFromFormat accepts those with only a
            // warning. Both cases fall back to the schedule.
            if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                return null;
            }

            $seconds = $date->getTimestamp() - $now->getTimestamp();
        }

        return $seconds >= self::RETRY_AFTER_MIN_SECONDS && $seconds <= self::RETRY_AFTER_MAX_SECONDS ? $seconds : null;
    }
}
