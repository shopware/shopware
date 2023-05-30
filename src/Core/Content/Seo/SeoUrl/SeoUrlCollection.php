<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrl;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SeoUrlEntity>
 */
#[Package('sales-channel')]
class SeoUrlCollection extends EntityCollection
{
    public function filterBySalesChannelId(string $id): SeoUrlCollection
    {
        return $this->filter(static fn (SeoUrlEntity $seoUrl) => $seoUrl->getSalesChannelId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'seo_url_collection';
    }

    protected function getExpectedClass(): string
    {
        return SeoUrlEntity::class;
    }
}
