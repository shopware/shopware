<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemOperator;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Sitemap\Struct\Sitemap;
use Shopware\Core\Defaults;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Asset\Package;

#[\Shopware\Core\Framework\Log\Package('discovery')]
class SitemapLister implements SitemapListerInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly Package $package,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getSitemaps(SalesChannelContext $salesChannelContext): array
    {
        $files = $this->filesystem->listContents('sitemap/salesChannel-' . $salesChannelContext->getSalesChannelId() . '-' . $salesChannelContext->getLanguageId());

        $sitemaps = [];

        /** @var SalesChannelDomainCollection $domains */
        $domains = $salesChannelContext->getSalesChannel()->getDomains();

        // domains of headless sales channels point at the external storefront, which does not serve the
        // sitemap files - they are always linked via the asset package (the host the files live on) instead
        $isHeadless = $salesChannelContext->getSalesChannel()->getTypeId() === Defaults::SALES_CHANNEL_TYPE_API;

        foreach ($files as $file) {
            if ($file->isDir()) {
                continue;
            }

            $filename = basename($file->path());

            $exploded = explode('-', $filename);

            if (!$isHeadless && isset($exploded[1]) && $domains->has($exploded[1])) {
                $domain = $domains->get($exploded[1]);

                $sitemaps[] = new Sitemap($domain->getUrl() . '/' . $file->path(), 0, new \DateTime('@' . ($file->lastModified() ?? $this->clock->now()->getTimestamp())));

                continue;
            }

            $sitemaps[] = new Sitemap($this->package->getUrl($file->path()), 0, new \DateTime('@' . ($file->lastModified() ?? $this->clock->now()->getTimestamp())));
        }

        return $sitemaps;
    }
}
