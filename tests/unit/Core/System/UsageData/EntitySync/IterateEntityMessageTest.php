<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\EntitySync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
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

        static::assertSame($dateTime->format(Defaults::STORAGE_DATE_TIME_FORMAT), $message->runDate->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        static::assertSame($dateTime->format(Defaults::STORAGE_DATE_TIME_FORMAT), $message->lastRun?->format(Defaults::STORAGE_DATE_TIME_FORMAT));
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
