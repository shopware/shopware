<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Stats;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageStatsEntity;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('framework')]
abstract class AbstractStatsRepository
{
    public function __construct(
        protected int $timeSpan,
        protected readonly ClockInterface $clock = new NativeClock(),
    ) {
    }

    abstract public function updateMessageStats(string $messageFqcn, int $timeInQueue): void;

    abstract public function getStats(): ?MessageStatsEntity;

    protected function getNow(): \DateTimeInterface
    {
        return $this->clock->now();
    }

    protected function getCutOffDate(): \DateTimeInterface
    {
        $cutOff = $this->getNow()->getTimestamp() - $this->timeSpan;

        return new \DateTimeImmutable('@' . $cutOff);
    }
}
