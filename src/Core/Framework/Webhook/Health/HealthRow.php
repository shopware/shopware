<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;

/**
 * One `webhook_health` row as read under lock; decisions clone it and set the fields they change.
 *
 * @internal
 */
#[Package('framework')]
final class HealthRow
{
    public function __construct(
        public EndpointState $state,
        public int $consecutiveTransientFailures,
        public int $consecutiveNonTransientFailures,
        public int $degradedCycleCount,
        public ?string $cooldownUntil,
        public ?string $suspendedSince,
        public ?string $disabledSince,
        public ?DisabledOrigin $disabledOrigin,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            EndpointState::from((string) $row['endpoint_state']),
            (int) $row['consecutive_transient_failures'],
            (int) $row['consecutive_non_transient_failures'],
            (int) $row['degraded_cycle_count'],
            \is_string($row['cooldown_until']) ? $row['cooldown_until'] : null,
            \is_string($row['suspended_since']) ? $row['suspended_since'] : null,
            \is_string($row['disabled_since']) ? $row['disabled_since'] : null,
            \is_string($row['disabled_origin']) ? DisabledOrigin::from($row['disabled_origin']) : null,
        );
    }

    public function cooldownElapsed(string $now): bool
    {
        return $this->cooldownUntil === null || $this->cooldownUntil <= $now;
    }

    public function toHealthy(bool $keepStreaks): self
    {
        $next = clone $this;
        $next->state = EndpointState::Healthy;
        if (!$keepStreaks) {
            $next->consecutiveTransientFailures = 0;
            $next->consecutiveNonTransientFailures = 0;
        }
        $next->degradedCycleCount = 0;
        $next->cooldownUntil = null;
        $next->suspendedSince = null;
        $next->disabledSince = null;
        $next->disabledOrigin = null;

        return $next;
    }

    public function toDisabled(DisabledOrigin $origin, string $now): self
    {
        $next = clone $this;
        $next->state = EndpointState::Disabled;
        $next->disabledSince = $now;
        $next->disabledOrigin = $origin;
        $next->cooldownUntil = null;

        return $next;
    }
}
