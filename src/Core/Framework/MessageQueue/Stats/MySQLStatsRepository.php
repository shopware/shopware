<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Stats;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type StatsData from StatsService
 *
 * @internal
 */
#[Package('core')]
class MySQLStatsRepository
{
    private const MESSAGE_TYPES_LIMIT = 100;

    public function __construct(
        private readonly Connection $connection,
        private readonly int $timeSpan,
    ) {
    }

    public function insertMessageStats(string $messageFqcn, int $timeInQueue, \DateTimeInterface $createdAt): void
    {
        $this->connection->insert('messenger_stats', [
            'message_type' => $messageFqcn,
            'time_in_queue' => $timeInQueue,
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function updateMessageStats(string $messageFqcn, int $timeInQueue, \DateTimeInterface $createdAt): void
    {
        $this->insertMessageStats($messageFqcn, $timeInQueue, $createdAt);
        $this->deleteStatsOlderThan($this->getStatsCutOff());
    }

    public function deleteStatsOlderThan(\DateTimeInterface $olderThan): void
    {
        $this->connection->createQueryBuilder()->delete('messenger_stats')
            ->where('created_at < :olderThan')
            ->setParameter('olderThan', $olderThan->format('Y-m-d H:i:s'))
            ->executeQuery();
    }

    /**
     * @return ?StatsData
     */
    public function getStats(): ?array
    {
        $newerThan = $this->getStatsCutOff();

        $query = $this->connection->createQueryBuilder()->select('COUNT(*) AS handled_count, MIN(created_at) AS handled_since, AVG(time_in_queue) AS average_time_in_queue')
            ->from('messenger_stats')
            ->where('created_at > :newerThan')
            ->setParameter('newerThan', $newerThan->format('Y-m-d H:i:s'));
        $vals = $query->executeQuery()->fetchAssociative();

        if ($vals === false) {
            return null;
        }
        $handledCount = (int) $vals['handled_count'];
        $handledSince = (string) $vals['handled_since'];
        $averageTimeInQueue = (float) $vals['average_time_in_queue'];

        $query = $this->connection->createQueryBuilder()->select('message_type AS name, COUNT(*) AS count')
            ->from('messenger_stats')
            ->where('created_at > :newerThan')
            ->groupBy('message_type')
            ->orderBy('created_at', 'DESC')
            ->setMaxResults(self::MESSAGE_TYPES_LIMIT)
            ->setParameter('newerThan', $newerThan->format('Y-m-d H:i:s'));

        $recentMessageTypes = $query->executeQuery()->fetchAllAssociative();
        $recentMessageTypes = array_map(static function (array $row): array {
            return [
                'name' => $row['name'],
                'count' => (int) $row['count'],
            ];
        }, $recentMessageTypes);

        return [
            'handledCount' => $handledCount,
            'handledSince' => $handledSince,
            'averageTimeInQueue' => $averageTimeInQueue,
            'recentMessageTypes' => $recentMessageTypes,
        ];
    }

    private function getStatsCutOff(): \DateTimeInterface
    {
        return new \DateTime('now - ' . $this->timeSpan . ' seconds');
    }
}
