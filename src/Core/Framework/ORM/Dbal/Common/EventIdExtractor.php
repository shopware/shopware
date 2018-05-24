<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\Common;

use Shopware\Content\Category\CategoryDefinition;
use Shopware\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Content\Product\ProductDefinition;
use Shopware\Framework\ORM\Write\GenericWrittenEvent;

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
