<?php declare(strict_types=1);

namespace Shopware\Core\Framework\RateLimiter\Policy;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\NoLock;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * @phpstan-type SystemConfigLimit array{domain: string, interval: string}
 */
#[Package('framework')]
class SystemConfigLimiter extends TimeBackoffLimiter
{
    /**
     * @param list<SystemConfigLimit> $limits
     */
    public function __construct(
        SystemConfigService $systemConfigService,
        string $id,
        array $limits,
        \DateInterval $reset,
        StorageInterface $storage,
        ?LockInterface $lock = null,
        ?ClockInterface $clock = null,
    ) {
        $convertedLimits = [];
        foreach ($limits as $limit) {
            $sysLimit = $systemConfigService->getInt($limit['domain'] ?? '');
            $convertedLimit = [
                'interval' => $limit['interval'],
                'limit' => $sysLimit !== 0 ? $sysLimit : \PHP_INT_MAX,
            ];

            $convertedLimits[] = $convertedLimit;
        }

        parent::__construct($id, $convertedLimits, $reset, $storage, $clock ?? new NativeClock(), $lock ?? new NoLock());
    }
}
