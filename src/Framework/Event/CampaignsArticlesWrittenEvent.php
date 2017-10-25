<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class CampaignsArticlesWrittenEvent extends EntityWrittenEvent
{
    const NAME = 's_campaigns_articles.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_campaigns_articles';
    }
}
