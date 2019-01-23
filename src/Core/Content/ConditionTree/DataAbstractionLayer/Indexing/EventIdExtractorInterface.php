<?php declare(strict_types=1);

namespace Shopware\Core\Content\ConditionTree\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;

interface EventIdExtractorInterface
{
    public function getEntityIds(EntityWrittenContainerEvent $generic): array;
}
