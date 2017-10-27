<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class CampaignsPositionsWrittenEvent extends WrittenEvent
{
    const NAME = 's_campaigns_positions.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_campaigns_positions';
    }
}
