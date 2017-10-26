<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class EmarketingBannersWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 's_emarketing_banners.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_emarketing_banners';
    }
}
