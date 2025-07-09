<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Stats;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageStatsEntity;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageTypeStatsCollection;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageTypeStatsEntity;

/**
 * @internal
 *
 * @codeCoverageIgnore tested via an integration test
 */
#[Package('framework')]
class MySQLStatsRepository extends AbstractStatsRepository
{
    private const MESSAGE_TYPES_LIMIT = 100;

    public function __construct(
        private readonly Connection $connection,
        int $timeSpan,
    ) {
        parent::__construct($timeSpan);
    }

    public function updateMessageStats(string $messageFqcn, int $timeInQueue): void
    {
        $cutoffDate = $this->getCutOffDate();
        $now = $this->getNow();
        $this->insertMessageStats($messageFqcn, $timeInQueue, $now);
        $this->deleteStatsOlderThan($cutoffDate);
    }

    public function getStats(): ?MessageStatsEntity
    {
        $newerThan = $this->getCutOffDate();

        $query = $this->connection->createQueryBuilder()->select('COUNT(*) AS handled_count, MIN(created_at) AS handled_since, AVG(time_in_queue) AS average_time_in_queue')
            ->from('messenger_stats')
            ->where('created_at > :newerThan')
            ->setParameter('newerThan', $newerThan->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        $vals = $query->executeQuery()->fetchAssociative();

        if (!isset($vals['handled_since'])) {
            return null;
        }

        $stats = new MessageStatsEntity(
            totalMessagesProcessed: (int) $vals['handled_count'],
            processedSince: new \DateTimeImmutable($vals['handled_since']),
            averageTimeInQueue: (float) $vals['average_time_in_queue'],
            messageTypeStats: new MessageTypeStatsCollection(),
        );

        $query = $this->connection->createQueryBuilder()->select('message_type AS name, COUNT(*) AS count')
            ->from('messenger_stats')
            ->where('created_at >= :newerThan')
            ->groupBy('message_type')
            ->orderBy('created_at', 'DESC')
            ->setMaxResults(self::MESSAGE_TYPES_LIMIT)
            ->setParameter('newerThan', $newerThan->format(Defaults::STORAGE_DATE_TIME_FORMAT));

        $recentMessageTypes = $query->executeQuery()->fetchAllAssociative();

        foreach ($recentMessageTypes as $row) {
            $stats->messageTypeStats->add(new MessageTypeStatsEntity(
                type: $row['name'],
                count: (int) $row['count'],
            ));
        }

        return $stats;
    }

    private function insertMessageStats(string $messageFqcn, int $timeInQueue, \DateTimeInterface $createdAt): void
    {
        $this->connection->insert('messenger_stats', [
            'message_type' => $messageFqcn,
            'time_in_queue' => $timeInQueue,
            'created_at' => $createdAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function deleteStatsOlderThan(\DateTimeInterface $olderThan): void
    {
        $this->connection->createQueryBuilder()->delete('messenger_stats')
            ->where('created_at < :olderThan')
            ->setParameter('olderThan', $olderThan->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->executeQuery();
    }
}
