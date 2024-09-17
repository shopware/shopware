<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Stats;

use Shopware\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageStats;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
#[Package('core')]
class StatsService
{
    public function __construct(
        private readonly MySQLStatsRepository $mySQLStatsRepository,
    ) {
    }

    public function getStats(): MessageStats
    {
        return $this->mySQLStatsRepository->getStats();
    }

    public function registerMessage(Envelope $envelope): void
    {
        $sentAtStamp = $envelope->last(SentAtStamp::class);
        if ($sentAtStamp === null) {
            return;
        }
        $now = \DateTimeImmutable::createFromFormat('U', (string) time());
        \assert($now instanceof \DateTimeImmutable);

        $timeInQueue = $now->getTimestamp() - $sentAtStamp->getSentAt();
        $messageFqcn = \get_class($envelope->getMessage());
        $this->mySQLStatsRepository->updateMessageStats($messageFqcn, $timeInQueue, $now);
    }
}
