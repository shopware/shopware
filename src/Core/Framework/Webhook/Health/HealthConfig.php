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
     */
    public function __construct(
        public array $cooldownScheduleSeconds,
        public int $degradedThreshold,
    ) {
    }
}
