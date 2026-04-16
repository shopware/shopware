<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cleanup;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Payment\Cleanup\CleanupPaymentTokenTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(CleanupPaymentTokenTaskHandler::class)]
class CleanupPaymentTokenTaskHandlerTest extends TestCase
{
    #[DataProvider('clockNowProvider')]
    public function testRunNormalizesClockTimeToUtcForStorage(string $now, string $expectedSqlParameter): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'DELETE FROM payment_token WHERE expires < :now',
                ['now' => $expectedSqlParameter]
            );

        $handler = new CleanupPaymentTokenTaskHandler(
            static::createStub(EntityRepository::class),
            new NullLogger(),
            $connection,
            new MockClock(new \DateTimeImmutable($now)),
        );

        $handler->run();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function clockNowProvider(): iterable
    {
        yield 'utc clock is formatted' => [
            '2025-01-15T12:00:00+00:00',
            '2025-01-15 12:00:00.000',
        ];

        yield 'non-utc offset is normalized to utc' => [
            '2025-01-15T13:00:00+01:00',
            '2025-01-15 12:00:00.000',
        ];

        yield 'sub-second precision is preserved in milliseconds' => [
            '2025-01-15T12:00:00.123456+00:00',
            '2025-01-15 12:00:00.123',
        ];
    }
}
