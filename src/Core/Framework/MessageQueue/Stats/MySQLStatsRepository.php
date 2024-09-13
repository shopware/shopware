<?php

namespace Shopware\Core\Framework\MessageQueue\Stats;

use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
class MySQLStatsRepository
{

    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function insertMessageStats(string $transportName, string $messageFqcn, int $timeInQueue, \DateTimeInterface $createdAt): void
    {
        $this->connection->insert('messenger_stats', [
            'message_type' => $messageFqcn,
            'time_in_queue' => $timeInQueue,
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function deleteStatsOlderThan(\DateTimeInterface $olderThan): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `messenger_stats` WHERE `created_at` < :olderThan',
            ['olderThan' => $olderThan->format('Y-m-d H:i:s')]
        );
    }

    public function getStats(\DateTimeInterface $newerThan, int $messageTypesLimit): array
    {
        $query = $this->connection->createQueryBuilder()->select('COUNT(*) AS handled_count, MIN(created_at) AS handled_since, AVG(time_in_queue) AS average_time_in_queue')
            ->from('messenger_stats')
            ->where('created_at > :newerThan')
            ->setParameter('newerThan', $newerThan->format('Y-m-d H:i:s'));
        $vals = $query->executeQuery()->fetchAssociative();

        if ($vals === false) {
            return [];
        }
        $handledCount = $vals['handled_count'];
        $handledSince = $vals['handled_since'];
        $averageTimeInQueue = $vals['average_time_in_queue'];

        $query = $this->connection->createQueryBuilder()->select('message_type AS name, COUNT(*) AS count')
            ->from('messenger_stats')
            ->where('created_at > :newerThan')
            ->groupBy('message_type')
            ->orderBy('created_at', 'DESC')
            ->setMaxResults($messageTypesLimit)
            ->setParameter('newerThan', $newerThan->format('Y-m-d H:i:s'));
        $recentMessageTypes = $query->executeQuery()->fetchAllAssociative();

        return [
            'handledCount' => $handledCount,
            'handledSince' => $handledSince,
            'averageTimeInQueue' => $averageTimeInQueue,
            'recentMessageTypes' => $recentMessageTypes,
        ];
    }
}
