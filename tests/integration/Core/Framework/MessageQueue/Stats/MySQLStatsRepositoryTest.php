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
#[Package('core')]
#[CoversClass(MySQLStatsRepository::class)]
class MySQLStatsRepositoryTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get('Doctrine\DBAL\Connection');
        $this->cleanTable();
    }

    protected function tearDown(): void
    {
        $this->cleanTable();
    }

    public function testUpdateMessageStatsDeletesExpired(): void
    {
        $repository = new MySQLStatsRepositoryTestable($this->connection, 20);

        $now = 1726671956;
        $repository->setNow($this->dateTimeFromTime($now - 21));
        $repository->updateMessageStats('myclassname', 3);

        $repository->setNow($this->dateTimeFromTime($now - 20));
        $repository->updateMessageStats('myclassname', 7);

        $repository->setNow($this->dateTimeFromTime($now));
        $repository->updateMessageStats('myclassname', 0);

        static::assertEquals(2, $this->countRecords($this->dateTimeFromTime($now - 21)));
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

        static::assertEquals(2, $stats->getTotalMessagesProcessed());
        static::assertEquals($now, $stats->getProcessedSince());
        static::assertEquals(5.5, $stats->getAverageTimeInQueue());
        static::assertCount(1, $stats->getMessageTypeStats());

        $typeStats = $stats->getMessageTypeStats()->first();
        static::assertNotNull($typeStats);
        static::assertEquals('test', $typeStats->getType());
        static::assertEquals(2, $typeStats->getCount());
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
