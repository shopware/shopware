<?php declare(strict_types=1);

namespace Shopware\Product\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class ProductEsdWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'product_esd.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_esd';
    }
}
