<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Util;

use Shopware\Core\Content\ConditionTree\DataAbstractionLayer\Indexing\EventIdExtractorInterface;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\ProductStream\ProductStreamCondition;

class EventIdExtractor implements EventIdExtractorInterface
{
    public function getEntityIds(EntityWrittenContainerEvent $generic): array
    {
        $ids = [];

        $event = $generic->getEventByDefinition(ProductStreamCondition::class);
        if ($event) {
            $ids = $event->getIds();
        }

        $event = $generic->getEventByDefinition(ProductStreamFilterDefinition::class);
        if ($event) {
            foreach ($event->getPayload() as $id) {
                if (isset($id['productStreamId'])) {
                    $ids[] = $id['productStreamId'];
                }
            }
        }

        return $ids;
    }
}
