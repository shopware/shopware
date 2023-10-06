<?php declare(strict_types=1);

namespace Shopware\Core\Framework\RateLimiter\Policy;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\RateLimiter\LimiterStateInterface;
use Symfony\Component\RateLimiter\Util\TimeUtil;

/**
 * @internal
 *
 * @phpstan-type TimeBackoffLimit array{limit: int, interval: string}
 */
#[Package('core')]
class TimeBackoff implements LimiterStateInterface
{
    private int $attempts;

    private int $timer;

    private int $expiresAt;

    private readonly int $unthrottledAttempts;

    private string $stringLimits;

    /**
     * @param list<TimeBackoffLimit> $limits
     */
    public function __construct(
        private readonly string $id,
        private array $limits,
        ?int $timer = null
    ) {
        $this->attempts = 0;
        $this->timer = $timer ?? time();
        $this->unthrottledAttempts = min(array_column($this->limits, 'limit')) ?: 0;
    }

    public function __sleep(): array
    {
        $this->stringLimits = \json_encode($this->limits, \JSON_THROW_ON_ERROR);

        return ['id', 'attempts', 'timer', 'expiresAt', 'unthrottledAttempts', 'stringLimits'];
    }

    public function __wakeup(): void
    {
        try {
            $this->limits = json_decode($this->stringLimits, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \BadMethodCallException('Cannot unserialize ' . self::class);
        }

        unset($this->stringLimits);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getExpirationTime(): ?int
    {
        return $this->expiresAt;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function setAttempts(int $attempts): void
    {
        $this->attempts = $attempts;
    }

    public function getAvailableAttempts(int $now): int
    {
        if ($this->attempts <= $this->unthrottledAttempts) {
            return $this->unthrottledAttempts - $this->attempts;
        }

        return $this->shouldThrottle($this->attempts, $now) !== true ? 1 : 0;
    }

    public function setTimer(int $time): void
    {
        $this->timer = $time;
    }

    public function setExpiresAt(int $time): void
    {
        $this->expiresAt = $time;
    }

    public function shouldThrottle(int $count, int $now): bool
    {
        $limit = $this->getLimit($count);

        $elapsed = $now - $this->timer;

        return $limit !== null && $elapsed < $this->intervalToSeconds($limit['interval']);
    }

    public function getRetryAfter(): \DateTimeImmutable
    {
        $limit = $this->getLimit($this->attempts + 1);

        if ($limit === null) {
            $retryAfter = time();
        } else {
            $retryAfter = $this->timer + $this->intervalToSeconds($limit['interval']);
        }

        $retryAfter = \DateTimeImmutable::createFromFormat('U', (string) $retryAfter);

        \assert($retryAfter instanceof \DateTimeImmutable);

        return $retryAfter;
    }

    public function getCurrentLimit(int $now): int
    {
        return $this->getLimit($this->attempts + 1) !== null ? 1 : $this->getAvailableAttempts($now);
    }

    /**
     * @return TimeBackoffLimit|null
     */
    public function getLimit(int $count): ?array
    {
        foreach ($this->limits as $key => $current) {
            $next = $this->limits[$key + 1] ?? null;

            if ($next === null && $count >= $current['limit']) {
                return $current;
            }

            if ($count > $current['limit'] && $next && $count <= $next['limit']) {
                return $current;
            }
        }

        return null;
    }

    private function intervalToSeconds(string $interval): int
    {
        return TimeUtil::dateIntervalToSeconds(\DateInterval::createFromDateString($interval));
    }
}
