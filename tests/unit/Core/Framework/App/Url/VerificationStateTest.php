<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Url;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Url\VerificationState;
use Shopware\Core\Framework\App\Url\VerificationStatus;

/**
 * @internal
 */
#[CoversClass(VerificationState::class)]
class VerificationStateTest extends TestCase
{
    public function testIsAndIsNotHardFail(): void
    {
        $now = new \DateTimeImmutable('2025-01-01T12:00:00Z');
        $state = new VerificationState(VerificationStatus::SOFT_FAIL, 0, $now);

        static::assertTrue($state->is(VerificationStatus::SOFT_FAIL));
        static::assertFalse($state->is(VerificationStatus::PASS));
        static::assertTrue($state->isNotHardFail());
    }

    public function testIsInBackoffTrueWhenNowBeforeAtPlusWait(): void
    {
        $at = new \DateTimeImmutable('2025-01-01T12:00:00Z');
        $state = new VerificationState(VerificationStatus::SOFT_FAIL, 1, $at);

        $now = new \DateTimeImmutable('2025-01-01T12:04:59Z');
        static::assertTrue($state->isInBackoff($now, 60 * 5));
    }

    public function testIsInBackoffFalseAtBoundaryAndAfter(): void
    {
        $at = new \DateTimeImmutable('2025-01-01T12:00:00Z');
        $state = new VerificationState(VerificationStatus::SOFT_FAIL, 1, $at);

        $boundary = new \DateTimeImmutable('2025-01-01T12:05:00Z');
        static::assertFalse($state->isInBackoff($boundary, 300));

        $after = new \DateTimeImmutable('2025-01-01T12:06:00Z');
        static::assertFalse($state->isInBackoff($after, 300));
    }
}
