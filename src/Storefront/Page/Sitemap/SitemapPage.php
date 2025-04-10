<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Sitemap;

use Shopware\Core\Content\Sitemap\Struct\Sitemap;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('discovery')]
class SitemapPage extends Struct
{
    /**
     * @var array<Sitemap>
     */
    protected array $sitemaps;

    /**
     * @return array<Sitemap>
     */
    public function getSitemaps(): array
    {
        return $this->sitemaps;
    }

    /**
     * @param array<Sitemap> $sitemaps
     */
    public function setSitemaps(array $sitemaps): void
    {
        $this->sitemaps = $sitemaps;
    }
}
