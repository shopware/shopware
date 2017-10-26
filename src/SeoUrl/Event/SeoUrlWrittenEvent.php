<?php declare(strict_types=1);

namespace Shopware\SeoUrl\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class SeoUrlWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'seo_url.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'seo_url';
    }
}
