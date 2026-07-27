<?php declare(strict_types=1);

namespace Shopware\Core\Framework\RateLimiter;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Policy\SystemConfigLimiter;
use Shopware\Core\Framework\RateLimiter\Policy\TimeBackoffLimiter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\NoLock;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\Policy\NoLimiter;
use Symfony\Component\RateLimiter\RateLimiterFactory as SymfonyRateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * @phpstan-type TimeBackoffLimit array{limit: int, interval: string}
 * @phpstan-type RateLimiterConfig array{
 *  enabled: bool,
 *  id: string,
 *  reset?: \DateInterval|string,
 *  policy: string,
 *  limits?: list<array{limit?: int, domain?: string, interval: string}>,
 *  limit?: int,
 *  rate?: array<string, string>
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

    #[NewOptionalParameter(version: 'v6.8.0', parameterName: 'salesChannelId', parameterType: '?string', defaultValue: null, description: 'Sales channel id used to resolve sales-channel scoped limits for the system_config policy. Callers passing it should also include it in the limiter key, as persisted buckets keep the limits they were created with.')]
    public function create(?string $key = null/* , ?string $salesChannelId = null */): LimiterInterface
    {
        /** @deprecated tag:v6.8.0 - Remove next line as $salesChannelId will become a part of method signature */
        /** @var string|null $salesChannelId */
        $salesChannelId = \func_get_args()[1] ?? null;

        if ($salesChannelId === '') {
            $salesChannelId = null;
        }

        if ($this->config['enabled'] === false) {
            return new NoLimiter();
        }

        $id = $this->config['id'] . '-' . (string) $key;
        $lock = $this->lockFactory ? $this->lockFactory->createLock($id) : new NoLock();

        if (isset($this->config['reset']) && !($this->config['reset'] instanceof \DateInterval)) {
            $this->config['reset'] = $this->clock->now()->diff($this->clock->now()->modify('+' . $this->config['reset']));
        }

        if ($this->config['policy'] === 'time_backoff' && isset($this->config['limits']) && isset($this->config['reset'])) {
            /** @var list<TimeBackoffLimit> $limits */
            $limits = $this->config['limits'];

            \assert($this->config['reset'] instanceof \DateInterval);

            return new TimeBackoffLimiter($id, $limits, $this->config['reset'], $this->storage, $this->clock, $lock);
        }

        if ($this->config['policy'] === 'system_config' && isset($this->config['limits']) && isset($this->config['reset'])) {
            \assert($this->config['reset'] instanceof \DateInterval);

            return new SystemConfigLimiter($this->systemConfigService, $id, $this->config['limits'], $this->config['reset'], $this->storage, $lock, $this->clock, $salesChannelId);
        }

        // prevent symfony errors due to customized values
        /** @var RateLimiterConfig $rateLimiterConfig */
        $rateLimiterConfig = \array_filter($this->config, static fn ($key): bool => !\in_array($key, ['enabled', 'reset', 'cache_pool', 'lock_factory', 'limits'], true), \ARRAY_FILTER_USE_KEY);

        $sfFactory = new SymfonyRateLimiterFactory($rateLimiterConfig, $this->storage, $this->lockFactory);

        return $sfFactory->create($key);
    }
}
