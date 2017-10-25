<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class CampaignsTemplatesWrittenEvent extends EntityWrittenEvent
{
    const NAME = 's_campaigns_templates.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_campaigns_templates';
    }
}
