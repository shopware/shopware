<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Sitemap\Struct\Sitemap;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Asset\Package;

class SitemapLister implements SitemapListerInterface
{
    /**
     * @var FilesystemOperator
     */
    private $filesystem;

    /**
     * @var Package
     */
    private $package;

    /**
     * @internal
     */
    public function __construct(FilesystemOperator $filesystem, Package $package)
    {
        $this->filesystem = $filesystem;
        $this->package = $package;
    }

    /**
     * {@inheritdoc}
     */
    public function getSitemaps(SalesChannelContext $salesChannelContext): array
    {
        $files = $this->filesystem->listContents('sitemap/salesChannel-' . $salesChannelContext->getSalesChannel()->getId() . '-' . $salesChannelContext->getLanguageId());

        $sitemaps = [];

        foreach ($files as $file) {
            if ($file->isDir()) {
                continue;
            }

            $sitemaps[] = new Sitemap($this->package->getUrl($file->path()), 0, new \DateTime('@' . ($file->lastModified() ?? time())));
        }

        return $sitemaps;
    }
}
