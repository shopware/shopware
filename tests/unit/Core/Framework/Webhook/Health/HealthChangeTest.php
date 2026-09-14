<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Health;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\HealthChange;
use Shopware\Core\Framework\Webhook\Health\HealthRow;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(HealthChange::class)]
class HealthChangeTest extends TestCase
{
    #[DataProvider('stateTransitionProvider')]
    public function testChangedStateComparesStatesNotRows(EndpointState $from, EndpointState $to, bool $expectedChangedState): void
    {
        $change = new HealthChange(
            self::createRow($from, consecutiveTransientFailures: 1),
            self::createRow($to, consecutiveTransientFailures: 99),
        );

        static::assertSame($expectedChangedState, $change->changedState());
    }

    /**
     * @return iterable<string, array{0: EndpointState, 1: EndpointState, 2: bool}>
     */
    public static function stateTransitionProvider(): iterable
    {
        yield 'same state with differing other fields is not a change' => [EndpointState::Degraded, EndpointState::Degraded, false];
        yield 'degraded to suspended is a change' => [EndpointState::Degraded, EndpointState::Suspended, true];
    }

    private static function createRow(EndpointState $state, int $consecutiveTransientFailures): HealthRow
    {
        return new HealthRow(
            $state,
            $consecutiveTransientFailures,
            consecutiveNonTransientFailures: 0,
            degradedCycleCount: 0,
            cooldownUntil: null,
            suspendedSince: null,
            disabledSince: null,
            disabledOrigin: null,
        );
    }
}
