<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
abstract class PostUpdateIndexer extends EntityIndexer
{
    final public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        return null;
    }
}
