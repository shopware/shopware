<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Struct\FrwState;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(FrwState::class)]
class FrwStateTest extends TestCase
{
    public function testOpenStateIsOpenOnly(): void
    {
        $state = FrwState::openState();

        static::assertTrue($state->isOpen());
        static::assertFalse($state->isCompleted());
        static::assertFalse($state->isFailed());
        static::assertNull($state->getCompletedAt());
        static::assertNull($state->getFailedAt());
        static::assertSame(0, $state->getFailureCount());
    }

    public function testCompletedStateUsesNowWhenNoTimestamp(): void
    {
        $state = FrwState::completedState();

        static::assertTrue($state->isCompleted());
        static::assertFalse($state->isFailed());
        static::assertFalse($state->isOpen());
        static::assertNotNull($state->getCompletedAt());
        static::assertSame(0, $state->getFailureCount());
    }

    public function testCompletedStateAcceptsTimestamp(): void
    {
        $when = new \DateTimeImmutable('2024-01-15');

        $state = FrwState::completedState($when);

        static::assertSame($when, $state->getCompletedAt());
    }

    public function testFailedStateReportsFailureCount(): void
    {
        $when = new \DateTimeImmutable('2024-02-20');

        $state = FrwState::failedState($when, 3);

        static::assertTrue($state->isFailed());
        static::assertFalse($state->isCompleted());
        static::assertFalse($state->isOpen());
        static::assertSame($when, $state->getFailedAt());
        static::assertSame(3, $state->getFailureCount());
    }

    public function testGetFailureCountReturnsZeroWhenNotFailed(): void
    {
        static::assertSame(0, FrwState::openState()->getFailureCount());
        static::assertSame(0, FrwState::completedState()->getFailureCount());
    }

    public function testApiAlias(): void
    {
        static::assertSame('store_frw_state', FrwState::openState()->getApiAlias());
    }
}
