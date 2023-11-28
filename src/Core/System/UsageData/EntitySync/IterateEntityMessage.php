<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\EntitySync;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\LowPriorityMessageInterface;

/**
 * @internal
 */
#[Package('data-services')]
class IterateEntityMessage implements LowPriorityMessageInterface
{
    public function __construct(
        private readonly string $entityName,
        private readonly Operation $operation,
        private readonly \DateTimeImmutable $runDate,
        private readonly \DateTimeImmutable|null $lastRun
    ) {
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function getOperation(): Operation
    {
        return $this->operation;
    }

    public function getRunDate(): \DateTimeImmutable
    {
        return $this->runDate;
    }

    public function getLastRun(): \DateTimeImmutable|null
    {
        return $this->lastRun;
    }
}
