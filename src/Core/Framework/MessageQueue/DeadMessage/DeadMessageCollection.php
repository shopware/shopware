<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\DeadMessage;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class DeadMessageCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return DeadMessageEntity::class;
    }
}
