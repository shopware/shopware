<?php declare(strict_types=1);

namespace Shopware\Product\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class ProductCategorySeoWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'product_category_seo.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_category_seo';
    }
}
