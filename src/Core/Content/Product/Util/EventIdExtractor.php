<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Util;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\ORM\Event\EntityWrittenContainerEvent;

class EventIdExtractor
{
    public function getProductIds(EntityWrittenContainerEvent $generic): array
    {
        $ids = [];

        $event = $generic->getEventByDefinition(ProductDefinition::class);
        if ($event) {
            $ids = $event->getIds();
        }

        $event = $generic->getEventByDefinition(ProductCategoryDefinition::class);
        if ($event) {
            foreach ($event->getIds() as $id) {
                $ids[] = $id['productId'];
            }
        }

        return $ids;
    }

    public function getCategoryIds(EntityWrittenContainerEvent $generic): array
    {
        $event = $generic->getEventByDefinition(CategoryDefinition::class);
        if ($event) {
            return $event->getIds();
        }

        return [];
    }
}
