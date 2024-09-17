<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\MessageQueue\Stats;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Stats\MySQLStatsRepository;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Bridge\PhpUnit\ClockMock;

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

    public function testUpdateMessageStats(): void
    {
        ClockMock::register(MySQLStatsRepository::class);
        ClockMock::register(__CLASS__);
        ClockMock::withClockMock(true);

        $repository = new MySQLStatsRepository($this->connection, 20);

        $now = time();
        $repository->updateMessageStats('myclassname', 3, $this->dateTimeFromTime($now - 21));
        $repository->updateMessageStats('myclassname', 7, $this->dateTimeFromTime($now - 20));
        $repository->updateMessageStats('myclassname', 0, $this->dateTimeFromTime($now));
        static::assertEquals(2, $this->countRecords($this->dateTimeFromTime($now - 21)));

        ClockMock::withClockMock(false);
    }

    public function testGetStats(): void
    {
        $repository = new MySQLStatsRepository($this->connection, 20);

        $now = $this->dateTimeFromTime(time());
        $expired = $this->dateTimeFromTime(time() - 30);
        $repository->updateMessageStats('test', 1, $now);
        $repository->updateMessageStats('test', 10, $now);
        $repository->updateMessageStats('test', 100, $expired);

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
        $vals = $query->executeQuery()->fetchAssociative();

        static::assertIsArray($vals);
        static::assertArrayHasKey('handled_count', $vals);

        return (int) $vals['handled_count'];
    }

    private function cleanTable(): void
    {
        $this->connection->executeStatement('DELETE FROM messenger_stats');
    }

    private function dateTimeFromTime(int $timestamp): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('U', (string) $timestamp);
        \assert($date instanceof \DateTimeImmutable);

        return $date;
    }
}
