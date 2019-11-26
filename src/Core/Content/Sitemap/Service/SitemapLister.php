<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Sitemap\Struct\Sitemap;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SitemapLister implements SitemapListerInterface
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * {@inheritdoc}
     */
    public function getSitemaps(SalesChannelContext $salesChannelContext): array
    {
        $files = $this->filesystem->listContents('sitemap/salesChannel-' . $salesChannelContext->getSalesChannel()->getId() . '-' . $salesChannelContext->getSalesChannel()->getLanguageId());

        $sitemaps = [];

        foreach ($files as $file) {
            if ($file['basename'][0] === '.') {
                continue;
            }

            $sitemaps[] = new Sitemap($file['path'], 0, new \DateTime('@' . $file['timestamp']));
        }

        return $sitemaps;
    }
}
