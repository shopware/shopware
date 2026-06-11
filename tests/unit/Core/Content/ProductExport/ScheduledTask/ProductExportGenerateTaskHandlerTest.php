<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\ScheduledTask;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\ProductExport\ScheduledTask\ProductExportGenerateTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[CoversClass(ProductExportGenerateTaskHandler::class)]
class ProductExportGenerateTaskHandlerTest extends TestCase
{
    /**
     * @param array{id: string, generated_at: ?string, interval: int, is_running: bool, updated_at: ?string, created_at: ?string} $productExportRow
     */
    #[DataProvider('shouldBeRunDataProvider')]
    public function testShouldBeRun(array $productExportRow, bool $expectedResult, bool $expectsReset): void
    {
        $connection = $this->createMock(Connection::class);

        $connection->method('fetchFirstColumn')
            ->willReturn([Uuid::randomHex()]);

        $connection->method('fetchAllAssociative')
            ->willReturn([$productExportRow]);

        $updateExpectation = $connection->expects($expectsReset ? $this->once() : $this->never())
            ->method('update');

        if ($expectsReset) {
            $updateExpectation->with(
                'product_export',
                ['is_running' => 0],
                ['id' => Uuid::fromHexToBytes($productExportRow['id'])],
                ['id' => ParameterType::BINARY]
            );
        }

        $messageBusMock = new CollectingMessageBus();

        $productExportGenerateTaskHandler = new ProductExportGenerateTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            $connection,
            $messageBusMock,
            new NativeClock(),
        );

        $productExportGenerateTaskHandler->run();

        if ($expectedResult) {
            static::assertCount(1, $messageBusMock->getMessages());
        } else {
            static::assertCount(0, $messageBusMock->getMessages());
        }
    }

    public static function shouldBeRunDataProvider(): \Generator
    {
        yield 'next generation not reached' => [
            // Should not run because: next generation time not reached (time + interval > now)
            self::productExportRow(false, '3022-07-18 10:59:30', 45),
            false,
            false,
        ];
        yield 'already running' => [
            // Should not run because: is running is true (another export is being generated atm.)
            self::productExportRow(true, ' ', 10),
            false,
            false,
        ];
        yield 'already running but recent' => [
            // Should not run because: isRunning is true and last activity is recent (below stale threshold)
            self::productExportRow(true, ' ', 10, '-10 seconds'),
            false,
            false,
        ];
        yield 'not generated before' => [
            // Should run because: has not been generated before
            self::productExportRow(false, null, 0),
            true,
            false,
        ];
        yield 'generation is due' => [
            // Should run because: next run is due (last generated + intervall < now)
            self::productExportRow(false, '1022-07-18 10:59:30', 10),
            true,
            false,
        ];
        yield 'already running but stale' => [
            // Should run because: isRunning is true but last activity is stale
            self::productExportRow(true, null, 10, '-1 hour'),
            true,
            true,
        ];
    }

    /**
     * @return array{id: string, generated_at: ?string, interval: int, is_running: bool, updated_at: ?string, created_at: ?string}
     */
    private static function productExportRow(bool $isRunning, ?string $generatedAt, int $interval, ?string $updatedAt = null, ?string $createdAt = null): array
    {
        return [
            'id' => 'afdd4e21be6b4ad59656fb856d0375e5',
            'generated_at' => $generatedAt,
            'interval' => $interval,
            'is_running' => $isRunning,
            'updated_at' => $updatedAt,
            'created_at' => $createdAt,
        ];
    }
}
