<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrl;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package sales-channel
 *
 * @extends EntityCollection<SeoUrlEntity>
 */
class SeoUrlCollection extends EntityCollection
{
    public function filterBySalesChannelId(string $id): SeoUrlCollection
    {
        return $this->filter(static function (SeoUrlEntity $seoUrl) use ($id) {
            return $seoUrl->getSalesChannelId() === $id;
        });
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
