<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Stats\Entity;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<MessageTypeStatsEntity>
 */
#[Package('framework')]
class MessageTypeStatsCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return MessageTypeStatsEntity::class;
    }
}
