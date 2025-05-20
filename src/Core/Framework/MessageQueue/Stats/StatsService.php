<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Stats;

use Shopware\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageStatsResponse;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
#[Package('framework')]
class StatsService
{
    public function __construct(
        private readonly AbstractStatsRepository $statsRepository,
        private readonly bool $enabled,
    ) {
    }

    public function getStats(): MessageStatsResponse
    {
        if (!$this->enabled) {
            return new MessageStatsResponse(enabled: false);
        }

        return new MessageStatsResponse(
            enabled: true,
            stats: $this->statsRepository->getStats()
        );
    }

    public function registerMessage(Envelope $envelope): void
    {
        if (!$this->enabled) {
            return;
        }

        $sentAtStamp = $envelope->last(SentAtStamp::class);
        if ($sentAtStamp === null) {
            return;
        }

        $timeInQueue = time() - $sentAtStamp->getSentAt()->getTimestamp();
        $messageFqcn = \get_class($envelope->getMessage());
        $this->statsRepository->updateMessageStats($messageFqcn, $timeInQueue);
    }
}
