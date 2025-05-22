<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Stats;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageStatsEntity;

/**
 * @internal
 */
#[Package('framework')]
abstract class AbstractStatsRepository
{
    public function __construct(protected int $timeSpan)
    {
    }

    abstract public function updateMessageStats(string $messageFqcn, int $timeInQueue): void;

    abstract public function getStats(): ?MessageStatsEntity;

    protected function getNow(): \DateTimeInterface
    {
        // Using time() function to make possible to mock the time with PHPUnit Symfony bridge
        return new \DateTimeImmutable('@' . time());
    }

    protected function getCutOffDate(): \DateTimeInterface
    {
        $cutOff = $this->getNow()->getTimestamp() - $this->timeSpan;

        return new \DateTimeImmutable('@' . $cutOff);
    }
}
