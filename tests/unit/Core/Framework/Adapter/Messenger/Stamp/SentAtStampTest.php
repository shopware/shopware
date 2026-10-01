<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Messenger\Stamp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SentAtStamp::class)]
class SentAtStampTest extends TestCase
{
    public function testConstructorWithDatetime(): void
    {
        $sentAt = new \DateTimeImmutable('@123456789');
        $stamp = new SentAtStamp($sentAt);

        static::assertSame($sentAt, $stamp->getSentAt());
    }

    public function testConstructorWithoutParameters(): void
    {
        $before = new \DateTimeImmutable();
        $stamp = new SentAtStamp();
        $after = new \DateTimeImmutable();

        $sentAt = $stamp->getSentAt();

        static::assertGreaterThanOrEqual($before, $sentAt);
        static::assertLessThanOrEqual($after, $sentAt);
    }
}
