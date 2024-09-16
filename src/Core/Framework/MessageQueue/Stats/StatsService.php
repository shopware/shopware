<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Stats;

use Shopware\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Envelope;

/**
 * @phpstan-type StatsData array{
 *     handledCount: int,
 *     handledSince: string,
 *     averageTimeInQueue: float,
 *     recentMessageTypes: array<array{name: string, count: int}>
 * }
 *
 * @internal
 */
#[Package('core')]
class StatsService
{
    public function __construct(
        private readonly MySQLStatsRepository $mySQLStatsRepository,
    ) {
    }

    /**
     * @return ?StatsData
     */
    public function getStats(): ?array
    {
        return $this->mySQLStatsRepository->getStats();
    }

    public function registerMessage(Envelope $envelope): void
    {
        $sentAtStamp = $envelope->last(SentAtStamp::class);
        if ($sentAtStamp === null) {
            return;
        }
        $now = new \DateTimeImmutable();

        $timeInQueue = $now->getTimestamp() - $sentAtStamp->getSentAt();
        $messageFqcn = \get_class($envelope->getMessage());
        $this->mySQLStatsRepository->updateMessageStats($messageFqcn, $timeInQueue, $now);
    }
}
