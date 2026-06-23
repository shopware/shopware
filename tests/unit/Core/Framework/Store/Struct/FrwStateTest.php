<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Struct\FrwState;

/**
 * @internal
 */
#[CoversClass(FrwState::class)]
class FrwStateTest extends TestCase
{
    #[TestDox('openState() is open, neither completed nor failed')]
    public function testOpenState(): void
    {
        $state = FrwState::openState();

        static::assertTrue($state->isOpen());
        static::assertFalse($state->isCompleted());
        static::assertFalse($state->isFailed());
        static::assertNull($state->getCompletedAt());
        static::assertNull($state->getFailedAt());
        static::assertSame(0, $state->getFailureCount());
    }

    #[TestDox('completedState() is completed and takes precedence over a previous failure')]
    public function testCompletedState(): void
    {
        $completedAt = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $state = FrwState::completedState($completedAt);

        static::assertTrue($state->isCompleted());
        static::assertFalse($state->isOpen());
        static::assertFalse($state->isFailed());
        static::assertSame($completedAt, $state->getCompletedAt());
    }

    #[TestDox('failedState() exposes the failure count only while failed')]
    public function testFailedStateExposesFailureCount(): void
    {
        $failedAt = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $state = FrwState::failedState($failedAt, 3);

        static::assertTrue($state->isFailed());
        static::assertFalse($state->isOpen());
        static::assertSame($failedAt, $state->getFailedAt());
        static::assertSame(3, $state->getFailureCount());
    }

    #[TestDox('getFailureCount() returns 0 when not in a failed state')]
    public function testFailureCountIsZeroWhenNotFailed(): void
    {
        static::assertSame(0, FrwState::openState()->getFailureCount());
        static::assertSame(0, FrwState::completedState(new \DateTimeImmutable())->getFailureCount());
    }

    #[TestDox('getApiAlias() is store_frw_state')]
    public function testApiAlias(): void
    {
        static::assertSame('store_frw_state', FrwState::openState()->getApiAlias());
    }
}
