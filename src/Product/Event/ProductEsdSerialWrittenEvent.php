<?php declare(strict_types=1);

namespace Shopware\Product\Event;

use Shopware\Api\Write\WrittenEvent;

class ProductEsdSerialWrittenEvent extends WrittenEvent
{
    const NAME = 'product_esd_serial.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_esd_serial';
    }
}
