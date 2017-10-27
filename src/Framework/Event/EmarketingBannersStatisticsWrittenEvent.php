<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class EmarketingBannersStatisticsWrittenEvent extends WrittenEvent
{
    const NAME = 's_emarketing_banners_statistics.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_emarketing_banners_statistics';
    }
}
