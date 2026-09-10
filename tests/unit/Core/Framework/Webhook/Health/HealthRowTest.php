<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Health;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\HealthRow;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(HealthRow::class)]
class HealthRowTest extends TestCase
{
    public function testCooldownElapsedTreatsExactExpiryMomentAsElapsed(): void
    {
        $now = '2026-01-01 12:00:00';

        static::assertTrue(self::createRow(cooldownUntil: $now)->cooldownElapsed($now));
        static::assertFalse(self::createRow(cooldownUntil: '2026-01-01 12:00:01')->cooldownElapsed($now));
        static::assertTrue(self::createRow(cooldownUntil: null)->cooldownElapsed($now));
    }

    public function testFromRowMapsAbsentTimestampsToNullAndCastsCounters(): void
    {
        $healthRow = HealthRow::fromRow([
            'endpoint_state' => 'healthy',
            'consecutive_transient_failures' => '2',
            'consecutive_non_transient_failures' => '3',
            'degraded_cycle_count' => '4',
            'cooldown_until' => null,
            'suspended_since' => null,
            'disabled_since' => null,
            'disabled_origin' => null,
        ]);

        static::assertNull($healthRow->cooldownUntil);
        static::assertNull($healthRow->suspendedSince);
        static::assertNull($healthRow->disabledSince);
        static::assertNull($healthRow->disabledOrigin);
        static::assertSame(2, $healthRow->consecutiveTransientFailures);
        static::assertSame(3, $healthRow->consecutiveNonTransientFailures);
        static::assertSame(4, $healthRow->degradedCycleCount);
    }

    public function testToHealthyClearsCooldownFieldsAndRespectsKeepStreaksFlag(): void
    {
        $row = self::createRow(
            consecutiveTransientFailures: 2,
            consecutiveNonTransientFailures: 3,
            degradedCycleCount: 4,
            cooldownUntil: '2026-01-01 12:00:00',
            suspendedSince: '2026-01-01 11:00:00',
            disabledSince: '2026-01-01 10:00:00',
            disabledOrigin: 'manual',
        );

        $keptStreaks = $row->toHealthy(keepStreaks: true);
        static::assertSame(EndpointState::Healthy, $keptStreaks->state);
        static::assertSame(2, $keptStreaks->consecutiveTransientFailures);
        static::assertSame(3, $keptStreaks->consecutiveNonTransientFailures);
        static::assertSame(0, $keptStreaks->degradedCycleCount);
        static::assertNull($keptStreaks->cooldownUntil);
        static::assertNull($keptStreaks->suspendedSince);
        static::assertNull($keptStreaks->disabledSince);
        static::assertNull($keptStreaks->disabledOrigin);

        $resetStreaks = $row->toHealthy(keepStreaks: false);
        static::assertSame(0, $resetStreaks->consecutiveTransientFailures);
        static::assertSame(0, $resetStreaks->consecutiveNonTransientFailures);

        static::assertSame(EndpointState::Degraded, $row->state);
        static::assertSame(2, $row->consecutiveTransientFailures);
        static::assertSame(3, $row->consecutiveNonTransientFailures);
        static::assertSame(4, $row->degradedCycleCount);
        static::assertSame('2026-01-01 12:00:00', $row->cooldownUntil);
        static::assertSame('2026-01-01 11:00:00', $row->suspendedSince);
        static::assertSame('2026-01-01 10:00:00', $row->disabledSince);
        static::assertSame('manual', $row->disabledOrigin);
    }

    private static function createRow(
        EndpointState $state = EndpointState::Degraded,
        int $consecutiveTransientFailures = 0,
        int $consecutiveNonTransientFailures = 0,
        int $degradedCycleCount = 0,
        ?string $cooldownUntil = null,
        ?string $suspendedSince = null,
        ?string $disabledSince = null,
        ?string $disabledOrigin = null,
    ): HealthRow {
        return new HealthRow(
            $state,
            $consecutiveTransientFailures,
            $consecutiveNonTransientFailures,
            $degradedCycleCount,
            $cooldownUntil,
            $suspendedSince,
            $disabledSince,
            $disabledOrigin,
        );
    }
}
