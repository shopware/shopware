<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart\Recurring;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\Recurring\RecurringDataStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(RecurringDataStruct::class)]
class RecurringDataStructTest extends TestCase
{
    public function testGetters(): void
    {
        $time = new \DateTime();
        $struct = new RecurringDataStruct('foo', $time);

        static::assertSame('foo', $struct->getSubscriptionId());
        static::assertSame($time, $struct->getNextSchedule());
    }
}
