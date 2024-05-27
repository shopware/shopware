<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\EntitySync;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\EntitySync\DispatchEntityMessage;
use Shopware\Core\System\UsageData\EntitySync\Operation;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\UsageData\EntitySync\DispatchEntityMessage
 */
#[Package('data-services')]
class DispatchEntityMessageTest extends TestCase
{
    /**
     * @dataProvider dateTimeProvider
     */
    public function testConvertsToDateTimeImmutable(\DateTimeInterface $runDate): void
    {
        $message = new DispatchEntityMessage(
            'product',
            Operation::CREATE,
            $runDate,
            []
        );

        static::assertEquals($runDate, $message->runDate);
    }

    /**
     * @return iterable<array{0: \DateTimeInterface}>
     */
    public static function dateTimeProvider(): iterable
    {
        yield 'DateTime could be used when the message will be deserialized' => [new \DateTime()];

        yield 'DateTimeImmutable will be used for the concrete implementation' => [new \DateTimeImmutable()];
    }
}
