<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\EntitySync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\EntitySync\IterateEntityMessage;
use Shopware\Core\System\UsageData\EntitySync\Operation;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(IterateEntityMessage::class)]
class IterateEntityMessageTest extends TestCase
{
    #[DataProvider('dateTimeProvider')]
    public function testConvertsToDateTimeImmutable(\DateTimeInterface $dateTime): void
    {
        $message = new IterateEntityMessage(
            'product',
            Operation::CREATE,
            $dateTime,
            $dateTime,
        );

        static::assertEquals($dateTime, $message->runDate);
        static::assertEquals($dateTime, $message->lastRun);
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
