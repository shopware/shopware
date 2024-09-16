<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Messenger\Stamp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(SentAtStamp::class)]
class SentAtStampTest extends TestCase
{
    public function testGetSentAt(): void
    {
        $timestamp = 123456789;
        $stamp = new SentAtStamp($timestamp);

        static::assertSame($timestamp, $stamp->getSentAt());
    }
}
