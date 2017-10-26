<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class CampaignsBannerWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 's_campaigns_banner.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_campaigns_banner';
    }
}
