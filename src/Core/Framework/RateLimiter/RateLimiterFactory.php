<?php declare(strict_types=1);

namespace Shopware\Core\Framework\RateLimiter;

use Shopware\Core\Framework\RateLimiter\Policy\TimeBackoffLimiter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\NoLock;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\Policy\NoLimiter;
use Symfony\Component\RateLimiter\RateLimiterFactory as SymfonyRateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

class RateLimiterFactory
{
    private array $config;

    private StorageInterface $storage;

    private ?LockFactory $lockFactory;

    public function __construct(array $config, StorageInterface $storage, ?LockFactory $lockFactory = null)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->lockFactory = $lockFactory;
    }

    public function create(?string $key = null): LimiterInterface
    {
        if ($this->config['enabled'] === false) {
            return new NoLimiter();
        }

        $id = $this->config['id'] . '-' . (string) $key;
        $lock = $this->lockFactory ? $this->lockFactory->createLock($id) : new NoLock();

        if (isset($this->config['reset']) && !($this->config['reset'] instanceof \DateInterval)) {
            $this->config['reset'] = (new \DateTimeImmutable())->diff(new \DateTimeImmutable('+' . $this->config['reset']));
        }

        if ($this->config['policy'] === 'time_backoff') {
            return new TimeBackoffLimiter($id, $this->config['limits'], $this->config['reset'], $this->storage, $lock);
        }

        // prevent symfony errors due to customized values
        $this->config = \array_filter($this->config, static function ($key): bool {
            return !\in_array($key, ['enabled', 'reset', 'cache_pool', 'lock_factory'], true);
        }, \ARRAY_FILTER_USE_KEY);

        $sfFactory = new SymfonyRateLimiterFactory($this->config, $this->storage, $this->lockFactory);

        return $sfFactory->create($key);
    }
}
