<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Sitemap\Struct\Sitemap;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Asset\Package;

class SitemapLister implements SitemapListerInterface
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * @var Package
     */
    private $package;

    public function __construct(FilesystemInterface $filesystem, Package $package)
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
            if ($file['basename'][0] === '.') {
                continue;
            }

            $sitemaps[] = new Sitemap($this->package->getUrl($file['path']), 0, new \DateTime('@' . $file['timestamp']));
        }

        return $sitemaps;
    }
}
