<?php declare(strict_types=1);

namespace Shopware\Product\Event;

use Shopware\Api\Write\WrittenEvent;

class ProductLinkTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'product_link_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_link_translation';
    }
}
