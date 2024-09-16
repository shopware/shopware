<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\Stats;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Stats\MySQLStatsRepository;
use Shopware\Core\Framework\MessageQueue\Stats\StatsService;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(StatsService::class)]
class StatsServiceTest extends TestCase
{
    public function testGetStats(): void
    {
        $returnVal = [
            'handledCount' => 5,
            'handledSince' => 3,
            'averageTimeInQueue' => 7.3,
            'recentMessageTypes' => [
                [
                    'name' => 'test',
                    'count' => 3,
                ],
            ],
        ];
        $repositoryMock = $this->createMock(MySQLStatsRepository::class);
        $repositoryMock->expects(static::once())
            ->method('getStats')
            ->willReturn($returnVal);
        $service = new StatsService($repositoryMock);
        $stats = $service->getStats();

        static::assertSame($returnVal, $stats);
    }

    public function testRegisterMessageWithoutStamp(): void
    {
        $repository = $this->createMock(MySQLStatsRepository::class);
        $repository->expects(static::never())
            ->method('updateMessageStats');

        $service = new StatsService($repository);
        $envelope = new Envelope(new \stdClass());

        $service->registerMessage($envelope);
    }

    public function testRegisterMessage(): void
    {
        ClockMock::register(StatsService::class);
        ClockMock::register(__CLASS__);
        ClockMock::withClockMock(true);

        $repository = $this->createMock(MySQLStatsRepository::class);

        $repository->expects(static::once())
            ->method('updateMessageStats')
            ->with(
                'stdClass',
                static::equalTo(time() - 123456789),
                static::callback(function (\DateTimeInterface $now) {
                    self::assertEquals($now->getTimestamp(), time());

                    return true;
                })
            );

        $service = new StatsService($repository);
        $envelope = new Envelope(new \stdClass(), [new SentAtStamp(123456789)]);
        $service->registerMessage($envelope);

        ClockMock::withClockMock(false);
    }
}
