<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\MessageQueue\Stats;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Stats\MySQLStatsRepository;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MySQLStatsRepository::class)]
class MySQLStatsRepositoryTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->cleanTable();
    }

    protected function tearDown(): void
    {
        $this->cleanTable();
    }

    public function testUpdateMessageStatsDeletesExpired(): void
    {
        $timespan = 20;
        $now = 1726671956;
        $dateInsideTimespan = $this->dateTimeFromTime($now - $timespan);
        $dateOutsideTimespan = $this->dateTimeFromTime($now - $timespan - 1);

        $repository = new MySQLStatsRepositoryTestable($this->connection, $timespan);

        // inserting "old" record
        $repository->setNow($dateOutsideTimespan);
        $repository->updateMessageStats('myclassname', 3);

        // inserting tow records on the timespan edges
        $repository->setNow($dateInsideTimespan);
        $repository->updateMessageStats('myclassname', 7);

        // this insert should delete the first record
        $repository->setNow($this->dateTimeFromTime($now));
        $repository->updateMessageStats('myclassname', 0);

        // we count all records equal or newer than the date
        static::assertSame(2, $this->countRecords($dateOutsideTimespan));
    }

    public function testGetStats(): void
    {
        $repository = new MySQLStatsRepositoryTestable($this->connection, 20);

        $now = $this->dateTimeFromTime(time());
        $expired = $this->dateTimeFromTime(time() - 30);

        $repository->setNow($expired);
        $repository->updateMessageStats('test', 100);

        $repository->setNow($now);
        $repository->updateMessageStats('test', 1);
        $repository->updateMessageStats('test', 10);

        $stats = $repository->getStats();

        static::assertNotNull($stats);
        static::assertSame(2, $stats->totalMessagesProcessed);
        static::assertEquals($now, $stats->processedSince);
        static::assertSame(5.5, $stats->averageTimeInQueue);
        static::assertCount(1, $stats->messageTypeStats);

        $typeStats = $stats->messageTypeStats->first();
        static::assertNotNull($typeStats);
        static::assertSame('test', $typeStats->type);
        static::assertSame(2, $typeStats->count);
    }

    private function countRecords(\DateTimeInterface $newerThan): int
    {
        $query = $this->connection->createQueryBuilder()->select('COUNT(*) AS handled_count')
            ->from('messenger_stats')
            ->where('created_at >= :newerThan')
            ->setParameter('newerThan', $newerThan->format('Y-m-d H:i:s'));
        $count = $query->executeQuery()->fetchOne();
        static::assertIsString($count);

        return (int) $count;
    }

    private function cleanTable(): void
    {
        $this->connection->executeStatement('DELETE FROM messenger_stats');
    }

    private function dateTimeFromTime(int $timestamp): \DateTimeImmutable
    {
        return new \DateTimeImmutable('@' . $timestamp);
    }
}

/**
 * @internal
 */
class MySQLStatsRepositoryTestable extends MySQLStatsRepository
{
    private \DateTimeInterface $now;

    public function setNow(\DateTimeInterface $now): void
    {
        $this->now = $now;
    }

    protected function getNow(): \DateTimeInterface
    {
        return $this->now;
    }
}
