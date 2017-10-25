<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class ProductDetailTranslationWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'product_detail_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_detail_translation';
    }
}
