<?php declare(strict_types=1);

namespace Shopware\Core\Framework\RateLimiter;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Policy\SystemConfigLimiter;
use Shopware\Core\Framework\RateLimiter\Policy\TimeBackoff;
use Shopware\Core\Framework\RateLimiter\Policy\TimeBackoffLimiter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\NoLock;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\Policy\NoLimiter;
use Symfony\Component\RateLimiter\RateLimiterFactory as SymfonyRateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * @phpstan-import-type TimeBackoffLimit from TimeBackoff
 * @phpstan-import-type SystemConfigLimit from SystemConfigLimiter
 *
 * @phpstan-type RateLimiterConfig array{
 *     id: string,
 *     enabled: bool,
 *     lock_factory?: string,
 *     policy: string,
 *     limit?: int,
 *     cache_pool?: string,
 *     interval?: scalar,
 *     reset?: \DateInterval|string,
 *     rate?: array{interval: scalar, amount?: int},
 *     limits?: list<TimeBackoffLimit|SystemConfigLimit>,
 * }
 */
#[Package('framework')]
class RateLimiterFactory
{
    /**
     * @internal
     *
     * @param RateLimiterConfig $config
     */
    public function __construct(
        private array $config,
        private readonly StorageInterface $storage,
        private readonly SystemConfigService $systemConfigService,
        private readonly ClockInterface $clock,
        private readonly ?LockFactory $lockFactory = null,
    ) {
    }

    public function create(?string $key = null): LimiterInterface
    {
        if ($this->config['enabled'] === false) {
            return new NoLimiter();
        }

        $id = $this->config['id'] . '-' . (string) $key;
        $lock = $this->lockFactory ? $this->lockFactory->createLock($id) : new NoLock();

        if (isset($this->config['reset']) && !($this->config['reset'] instanceof \DateInterval)) {
            $this->config['reset'] = $this->clock->now()->diff($this->clock->now()->modify('+' . $this->config['reset']));
        }

        if ($this->config['policy'] === 'time_backoff' && isset($this->config['limits'], $this->config['reset'])) {
            /** @var list<TimeBackoffLimit> $limits */
            $limits = $this->config['limits'];

            \assert($this->config['reset'] instanceof \DateInterval);

            return new TimeBackoffLimiter($id, $limits, $this->config['reset'], $this->storage, $this->clock, $lock);
        }

        if ($this->config['policy'] === 'system_config' && isset($this->config['limits'], $this->config['reset'])) {
            /** @var list<SystemConfigLimit> $limits */
            $limits = $this->config['limits'];

            \assert($this->config['reset'] instanceof \DateInterval);

            return new SystemConfigLimiter($this->systemConfigService, $id, $limits, $this->config['reset'], $this->storage, $lock, $this->clock);
        }

        // prevent Symfony errors due to customized values
        $rateLimiterConfig = \array_filter($this->config, static fn ($key): bool => !\in_array($key, ['enabled', 'reset', 'cache_pool', 'lock_factory', 'limits'], true), \ARRAY_FILTER_USE_KEY);

        $sfFactory = new SymfonyRateLimiterFactory($rateLimiterConfig, $this->storage, $this->lockFactory);

        return $sfFactory->create($key);
    }
}
