<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Stats;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\MessageQueueException;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageStats;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageTypeStats;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageTypeStatsCollection;

/**
 * @internal
 */
#[Package('core')]
class MySQLStatsRepository
{
    private const MESSAGE_TYPES_LIMIT = 100;
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly Connection $connection,
        private readonly int $timeSpan,
    ) {
    }

    private function insertMessageStats(string $messageFqcn, int $timeInQueue, \DateTimeInterface $createdAt): void
    {
        $this->connection->insert('messenger_stats', [
            'message_type' => $messageFqcn,
            'time_in_queue' => $timeInQueue,
            'created_at' => $createdAt->format(self::DATE_FORMAT),
        ]);
    }

    public function updateMessageStats(string $messageFqcn, int $timeInQueue, \DateTimeInterface $createdAt): void
    {
        $cutoffDate = $this->getCutOffDate();
        if ($createdAt >= $cutoffDate) {
            $this->insertMessageStats($messageFqcn, $timeInQueue, $createdAt);
        }
        $this->deleteStatsOlderThan($cutoffDate);
    }

    private function deleteStatsOlderThan(\DateTimeInterface $olderThan): void
    {
        $this->connection->createQueryBuilder()->delete('messenger_stats')
            ->where('created_at < :olderThan')
            ->setParameter('olderThan', $olderThan->format(self::DATE_FORMAT))
            ->executeQuery();
    }

    public function getStats(): MessageStats
    {
        $newerThan = $this->getCutOffDate();

        $query = $this->connection->createQueryBuilder()->select('COUNT(*) AS handled_count, MIN(created_at) AS handled_since, AVG(time_in_queue) AS average_time_in_queue')
            ->from('messenger_stats')
            ->where('created_at > :newerThan')
            ->setParameter('newerThan', $newerThan->format(self::DATE_FORMAT));
        $vals = $query->executeQuery()->fetchAssociative();

        if (!isset($vals['handled_since'])) {
            throw MessageQueueException::queueMessageStatsNotFound();
        }

        $stats = new MessageStats(
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
            ->setParameter('newerThan', $newerThan->format(self::DATE_FORMAT));

        $recentMessageTypes = $query->executeQuery()->fetchAllAssociative();

        foreach ($recentMessageTypes as $row) {
            $stats->getMessageTypeStats()->add(new MessageTypeStats(
                type: $row['name'],
                count: (int) $row['count'],
            ));
        }

        return $stats;
    }

    private function getCutOffDate(): \DateTimeInterface
    {
        $cutOff = time() - $this->timeSpan;
        $cutOffDate = \DateTimeImmutable::createFromFormat('U', (string) $cutOff);
        \assert($cutOffDate instanceof \DateTimeImmutable);

        return $cutOffDate;
    }
}
