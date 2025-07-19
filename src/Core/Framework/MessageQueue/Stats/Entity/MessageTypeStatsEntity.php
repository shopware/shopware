<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Stats\Entity;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('framework')]
class MessageTypeStatsEntity extends Struct
{
    public function __construct(
        public readonly string $type,
        public readonly int $count,
    ) {
    }
}
