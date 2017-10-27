<?php declare(strict_types=1);

namespace Shopware\SeoUrl\Event;

use Shopware\Api\Write\WrittenEvent;

class SeoUrlWrittenEvent extends WrittenEvent
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
