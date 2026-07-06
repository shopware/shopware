<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\WebhookException;

/**
 * Typed view of the `shopware.webhook.health.*` container parameters, validated in the
 * constructor.
 *
 * There is deliberately no max-cycles setting: the length of the cooldown schedule IS
 * the DEGRADED cycle budget. To change the budget, edit the schedule.
 *
 * Operational constraint: the first cooldown tier must be longer than the slowest
 * expected HTTP delivery. Otherwise a released trial's own result could land after the
 * next cooldown has already passed, and it would be absorbed as a straggler instead of
 * counted.
 *
 * @internal
 */
#[Package('framework')]
final class HealthConfig
{
    /**
     * @param list<int> $cooldownScheduleSeconds
     */
    public function __construct(
        public readonly array $cooldownScheduleSeconds,
        public readonly int $degradedThreshold,
        public readonly int $nonTransientThreshold,
        public readonly int $maxSuspendedDays,
    ) {
        if ($cooldownScheduleSeconds === []) {
            throw WebhookException::invalidHealthConfig('cooldown_schedule_seconds must not be empty');
        }

        if ($degradedThreshold < 1) {
            throw WebhookException::invalidHealthConfig('degraded_threshold must be at least 1');
        }

        if ($nonTransientThreshold < 1) {
            throw WebhookException::invalidHealthConfig('non_transient_threshold must be at least 1');
        }

        if ($maxSuspendedDays < 1 || $maxSuspendedDays > 14) {
            throw WebhookException::invalidHealthConfig('max_suspended_days must be between 1 and 14');
        }
    }
}
