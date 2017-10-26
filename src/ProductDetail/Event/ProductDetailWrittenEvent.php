<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class ProductDetailWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'product_detail.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_detail';
    }
}
