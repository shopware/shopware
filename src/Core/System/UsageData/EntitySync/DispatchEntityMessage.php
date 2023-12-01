<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\EntitySync;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\LowPriorityMessageInterface;

/**
 * @internal
 *
 * @phpstan-type PrimaryKeyList array<int, array<string, string>>
 */
#[Package('data-services')]
class DispatchEntityMessage implements LowPriorityMessageInterface
{
    /**
     * @param PrimaryKeyList $primaryKeys
     */
    public function __construct(
        private readonly string $entityName,
        private readonly Operation $operation,
        private readonly \DateTimeImmutable $runDate,
        private readonly array $primaryKeys,
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

    /**
     * @return PrimaryKeyList
     */
    public function getPrimaryKeys(): array
    {
        return $this->primaryKeys;
    }
}
