<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cleanup;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Payment\Cleanup\CleanupPaymentTokenTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(CleanupPaymentTokenTaskHandler::class)]
#[Package('checkout')]
class CleanupPaymentTokenTaskHandlerTest extends TestCase
{
    #[DataProvider('clockNowCases')]
    public function testRunFormatsNowAsUtcSqlParameter(\DateTimeImmutable $now, string $expectedSqlParameter): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'DELETE FROM payment_token WHERE expires < :now',
                ['now' => $expectedSqlParameter]
            );

        $handler = new CleanupPaymentTokenTaskHandler(
            $this->createMock(EntityRepository::class),
            new NullLogger(),
            $connection,
            new MockClock($now),
        );

        $handler->run();
    }

    /**
     * @return iterable<string, array{\DateTimeImmutable, string}>
     */
    public static function clockNowCases(): iterable
    {
        yield 'utc clock is formatted' => [
            new \DateTimeImmutable('2025-01-15T12:00:00+00:00'),
            '2025-01-15 12:00:00.000',
        ];

        yield 'positive offset is normalized to utc' => [
            new \DateTimeImmutable('2025-01-15T13:00:00+01:00'),
            '2025-01-15 12:00:00.000',
        ];

        yield 'negative offset is normalized to utc' => [
            new \DateTimeImmutable('2025-01-15T07:00:00-05:00'),
            '2025-01-15 12:00:00.000',
        ];

        yield 'sub-second precision is preserved in milliseconds' => [
            new \DateTimeImmutable('2025-01-15T12:00:00.123456+00:00'),
            '2025-01-15 12:00:00.123',
        ];
    }
}
