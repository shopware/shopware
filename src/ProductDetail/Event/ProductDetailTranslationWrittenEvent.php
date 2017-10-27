<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Event;

use Shopware\Api\Write\WrittenEvent;

class ProductDetailTranslationWrittenEvent extends WrittenEvent
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
