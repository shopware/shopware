<?php declare(strict_types=1);

namespace Shopware\Core\Framework\RateLimiter\Policy;

use Symfony\Component\RateLimiter\LimiterStateInterface;
use Symfony\Component\RateLimiter\Util\TimeUtil;

/**
 * @internal
 */
class TimeBackoff implements LimiterStateInterface
{
    private string $id;

    private array $limits;

    private int $attempts;

    private int $timer;

    private int $expiresAt;

    private int $unthrottledAttempts;

    private string $stringLimits;

    public function __construct(string $id, array $limits, ?int $timer = null)
    {
        $this->id = $id;
        $this->limits = $limits;
        $this->attempts = 0;
        $this->timer = $timer ?? time();
        $this->unthrottledAttempts = min(array_column($this->limits, 'limit'));
    }

    public function __sleep(): array
    {
        $this->stringLimits = \json_encode($this->limits, \JSON_THROW_ON_ERROR);

        return ['id', 'attempts', 'timer', 'expiresAt', 'unthrottledAttempts', 'stringLimits'];
    }

    public function __wakeup(): void
    {
        if (($limits = \json_decode($this->stringLimits, true)) === null) {
            throw new \BadMethodCallException('Cannot unserialize ' . __CLASS__);
        }

        $this->limits = $limits;
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

    public function getLimit(int $count): ?array
    {
        foreach ($this->limits as $key => $current) {
            $next = $this->limits[$key + 1] ?? null;

            if ($next === null && $count >= $current['limit']) {
                return $current;
            }

            if ($count > $current['limit'] && $count <= $next['limit']) {
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
