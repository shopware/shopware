<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class HealthConfig
{
    /**
     * @param non-empty-list<positive-int> $cooldownScheduleSeconds
     * @param positive-int $degradedThreshold
     * @param positive-int $nonTransientThreshold
     * @param int<1, 14> $maxSuspendedDays
     */
    public function __construct(
        public array $cooldownScheduleSeconds,
        public int $degradedThreshold,
        public int $nonTransientThreshold,
        public int $maxSuspendedDays,
    ) {
    }
}
