<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Stats;

use Shopware\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageStatsEntity;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
#[Package('core')]
class StatsService
{
    public function __construct(
        private readonly AbstractStatsRepository $statsRepository,
    ) {
    }

    public function getStats(): MessageStatsEntity
    {
        return $this->statsRepository->getStats();
    }

    public function registerMessage(Envelope $envelope): void
    {
        $sentAtStamp = $envelope->last(SentAtStamp::class);
        if ($sentAtStamp === null) {
            return;
        }

        $timeInQueue = time() - $sentAtStamp->getSentAt();
        $messageFqcn = \get_class($envelope->getMessage());
        $this->statsRepository->updateMessageStats($messageFqcn, $timeInQueue);
    }
}
