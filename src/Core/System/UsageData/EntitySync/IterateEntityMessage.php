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
        public readonly string $entityName,
        public readonly Operation $operation,
        public readonly \DateTimeImmutable $runDate,
        public readonly ?\DateTimeImmutable $lastRun,
        public readonly ?string $shopId = null
    ) {
    }
}
