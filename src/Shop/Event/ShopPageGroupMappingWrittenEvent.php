<?php declare(strict_types=1);

namespace Shopware\Shop\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class ShopPageGroupMappingWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'shop_page_group_mapping.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'shop_page_group_mapping';
    }
}
