<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Stats\Entity;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<MessageTypeStats>
 */
#[Package('core')]
class MessageTypeStatsCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return MessageTypeStats::class;
    }
}
