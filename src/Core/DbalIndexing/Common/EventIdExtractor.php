<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Common;

use Shopware\Content\Category\Definition\CategoryDefinition;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Product\Definition\ProductCategoryDefinition;
use Shopware\Api\Product\Definition\ProductDefinition;

class EventIdExtractor
{
    public function getProductIds(GenericWrittenEvent $generic): array
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

    public function getCategoryIds(GenericWrittenEvent $generic): array
    {
        $event = $generic->getEventByDefinition(CategoryDefinition::class);
        if ($event) {
            return $event->getIds();
        }

        return [];
    }
}
