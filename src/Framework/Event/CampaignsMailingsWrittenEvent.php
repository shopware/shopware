<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class CampaignsMailingsWrittenEvent extends WrittenEvent
{
    const NAME = 's_campaigns_mailings.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_campaigns_mailings';
    }
}
