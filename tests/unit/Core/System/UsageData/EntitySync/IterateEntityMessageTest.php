<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\EntitySync;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\EntitySync\IterateEntityMessage;
use Shopware\Core\System\UsageData\EntitySync\Operation;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\UsageData\EntitySync\IterateEntityMessage
 */
#[Package('data-services')]
class IterateEntityMessageTest extends TestCase
{
    public function testGetters(): void
    {
        $runDate = new \DateTimeImmutable('2023-07-25');
        $lastRun = new \DateTimeImmutable('2023-07-24');

        $message = new IterateEntityMessage(
            'product',
            Operation::CREATE,
            $runDate,
            $lastRun
        );

        static::assertEquals('product', $message->getEntityName());
        static::assertEquals(Operation::CREATE, $message->getOperation());
        static::assertEquals($runDate, $message->getRunDate());
        static::assertEquals($lastRun, $message->getLastRun());
    }
}
