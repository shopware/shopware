<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\Stats;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageStatsEntity;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageTypeStatsCollection;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageTypeStatsEntity;
use Shopware\Core\Framework\MessageQueue\Stats\MySQLStatsRepository;
use Shopware\Core\Framework\MessageQueue\Stats\StatsService;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StatsService::class)]
class StatsServiceTest extends TestCase
{
    public function testGetStatsWhenEnabled(): void
    {
        $returnVal = new MessageStatsEntity(
            totalMessagesProcessed: 5,
            processedSince: new \DateTimeImmutable('3 seconds ago'),
            averageTimeInQueue: 7.3,
            messageTypeStats: new MessageTypeStatsCollection([
                new MessageTypeStatsEntity(
                    type: 'test',
                    count: 3,
                ),
            ]),
        );

        $repositoryMock = $this->createMock(MySQLStatsRepository::class);
        $repositoryMock->expects($this->once())
            ->method('getStats')
            ->willReturn($returnVal);
        $service = new StatsService($repositoryMock, true);
        $response = $service->getStats();

        static::assertTrue($response->enabled);
        static::assertSame($returnVal, $response->stats);
    }

    public function testGetStatsWhenEnabledButNoStats(): void
    {
        $repositoryMock = $this->createMock(MySQLStatsRepository::class);
        $repositoryMock->expects($this->once())
            ->method('getStats')
            ->willReturn(null);
        $service = new StatsService($repositoryMock, true);
        $response = $service->getStats();

        static::assertTrue($response->enabled);
        static::assertNull($response->stats);
    }

    public function testGetStatsWhenDisabled(): void
    {
        $repositoryMock = $this->createMock(MySQLStatsRepository::class);
        $repositoryMock->expects($this->never())
            ->method('getStats');

        $service = new StatsService($repositoryMock, false);
        $response = $service->getStats();

        static::assertFalse($response->enabled);
        static::assertNull($response->stats);
    }

    public function testRegisterMessageWithoutStamp(): void
    {
        $repository = $this->createMock(MySQLStatsRepository::class);
        $repository->expects($this->never())
            ->method('updateMessageStats');

        $service = new StatsService($repository, true);
        $envelope = new Envelope(new \stdClass());

        $service->registerMessage($envelope);
    }

    public function testRegisterMessageWhenDisabled(): void
    {
        $repository = $this->createMock(MySQLStatsRepository::class);
        $repository->expects($this->never())
            ->method('updateMessageStats');

        $service = new StatsService($repository, false);
        $envelope = new Envelope(new \stdClass(), [
            new SentAtStamp(new \DateTimeImmutable('@' . 123456789)),
        ]);

        $service->registerMessage($envelope);
    }

    public function testRegisterMessage(): void
    {
        ClockMock::register(StatsService::class);
        ClockMock::register(__CLASS__);
        ClockMock::withClockMock(true);

        $repository = $this->createMock(MySQLStatsRepository::class);

        $repository->expects($this->once())
            ->method('updateMessageStats')
            ->with(
                'stdClass',
                static::equalTo(time() - 123456789),
            );

        $service = new StatsService($repository, true);
        $envelope = new Envelope(new \stdClass(), [
            new SentAtStamp(new \DateTimeImmutable('@' . 123456789)),
        ]);
        $service->registerMessage($envelope);

        ClockMock::withClockMock(false);
    }
}
