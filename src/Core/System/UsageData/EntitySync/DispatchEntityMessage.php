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
        public readonly string $entityName,
        public readonly Operation $operation,
        public readonly \DateTimeImmutable $runDate,
        public readonly array $primaryKeys,
        public readonly ?string $shopId = null
    ) {
    }
}
