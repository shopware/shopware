<?php declare(strict_types=1);

namespace Shopware\Core\Framework\RateLimiter\Policy;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\NoLock;
use Symfony\Component\RateLimiter\Exception\ReserveNotSupportedException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\Policy\ResetLimiterTrait;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\Reservation;
use Symfony\Component\RateLimiter\Storage\StorageInterface;
use Symfony\Component\RateLimiter\Util\TimeUtil;

/**
 * @internal
 *
 * @phpstan-import-type TimeBackoffLimit from TimeBackoff
 */
#[Package('core')]
class TimeBackoffLimiter implements LimiterInterface
{
    use ResetLimiterTrait;

    private readonly int $reset;

    /**
     * @param list<TimeBackoffLimit> $limits
     */
    public function __construct(
        string $id,
        private readonly array $limits,
        \DateInterval $reset,
        StorageInterface $storage,
        LockInterface|null $lock = new NoLock()
    ) {
        $this->id = $id;
        $this->reset = TimeUtil::dateIntervalToSeconds($reset);
        $this->storage = $storage;
        $this->lock = $lock;
    }

    public function reserve(int $tokens = 1, ?float $maxTime = null): Reservation
    {
        throw new ReserveNotSupportedException(self::class);
    }

    public function consume(int $tokens = 1): RateLimit
    {
        $this->lock?->acquire(true);

        try {
            $backoff = $this->storage->fetch($this->id);
            if (!$backoff instanceof TimeBackoff) {
                $backoff = new TimeBackoff($this->id, $this->limits, $this->reset);
            }

            $now = time();
            $limit = $backoff->getCurrentLimit($now);

            if ($tokens > $limit) {
                throw new \InvalidArgumentException(sprintf('Cannot reserve more tokens (%d) than the size of the rate limiter (%d).', $tokens, $limit));
            }

            $attempts = $backoff->getAttempts();
            if ($backoff->shouldThrottle($attempts + $tokens, $now)) {
                return new RateLimit($backoff->getAvailableAttempts($now), $backoff->getRetryAfter(), false, $backoff->getCurrentLimit($now));
            }

            $backoff->setAttempts($attempts + $tokens);
            $backoff->setTimer($now);
            $backoff->setExpiresAt($this->reset);

            $this->storage->save($backoff);

            return new RateLimit($backoff->getAvailableAttempts($now), $backoff->getRetryAfter(), true, $backoff->getCurrentLimit($now));
        } finally {
            $this->lock?->release();
        }
    }
}
