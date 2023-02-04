<?php declare(strict_types=1);

namespace Shopware\Core\Framework\RateLimiter\Policy;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

#[Package('core')]
class SystemConfigLimiter extends TimeBackoffLimiter
{
    /**
     * @param list<array{domain?: string, limit?: int, interval: string}> $limits
     */
    public function __construct(
        SystemConfigService $systemConfigService,
        string $id,
        array $limits,
        \DateInterval $reset,
        StorageInterface $storage,
        ?LockInterface $lock = null
    ) {
        foreach ($limits as $idx => $limit) {
            if (!isset($limit['domain'])) {
                continue;
            }

            $sysLimit = $systemConfigService->get($limit['domain']);
            $limits[$idx]['limit'] = $sysLimit && (int) $sysLimit !== 0 ? (int) $sysLimit : \PHP_INT_MAX;
            unset($limits[$idx]['domain']);
        }

        /** @var list<array{limit: int, interval: string}> $limits */
        parent::__construct($id, $limits, $reset, $storage, $lock);
    }
}
