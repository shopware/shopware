<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Sitemap;

use Shopware\Core\Framework\Struct\Struct;

class SitemapPage extends Struct
{
    /**
     * @var array
     */
    protected $sitemaps;

    public function getSitemaps(): array
    {
        return $this->sitemaps;
    }

    public function setSitemaps(array $sitemaps): void
    {
        $this->sitemaps = $sitemaps;
    }
}
