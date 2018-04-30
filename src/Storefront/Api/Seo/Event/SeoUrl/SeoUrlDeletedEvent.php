<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo\Event\SeoUrl;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Storefront\Api\Seo\Definition\SeoUrlDefinition;

class SeoUrlDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'seo_url.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return SeoUrlDefinition::class;
    }
}
