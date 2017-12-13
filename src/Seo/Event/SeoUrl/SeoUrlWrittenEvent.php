<?php declare(strict_types=1);

namespace Shopware\Seo\Event\SeoUrl;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Seo\Definition\SeoUrlDefinition;

class SeoUrlWrittenEvent extends WrittenEvent
{
    const NAME = 'seo_url.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return SeoUrlDefinition::class;
    }
}
