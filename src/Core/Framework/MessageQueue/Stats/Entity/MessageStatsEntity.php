<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Stats\Entity;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('core')]
class MessageStatsEntity extends Struct
{
    protected int $totalMessagesProcessed;

    protected \DateTimeInterface $processedSince;

    protected float $averageTimeInQueue;

    protected MessageTypeStatsCollection $messageTypeStats;

    public function __construct(
        int $totalMessagesProcessed,
        \DateTimeInterface $processedSince,
        float $averageTimeInQueue,
        MessageTypeStatsCollection $messageTypeStats
    ) {
        $this->totalMessagesProcessed = $totalMessagesProcessed;
        $this->processedSince = $processedSince;
        $this->averageTimeInQueue = $averageTimeInQueue;
        $this->messageTypeStats = $messageTypeStats;
    }

    public function getTotalMessagesProcessed(): int
    {
        return $this->totalMessagesProcessed;
    }

    public function getProcessedSince(): \DateTimeInterface
    {
        return $this->processedSince;
    }

    public function getAverageTimeInQueue(): float
    {
        return $this->averageTimeInQueue;
    }

    public function getMessageTypeStats(): MessageTypeStatsCollection
    {
        return $this->messageTypeStats;
    }
}
