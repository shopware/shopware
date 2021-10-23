<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SitemapHandleFactory implements SitemapHandleFactoryInterface
{
    private ?int $maxUrls;

    public function __construct(?int $maxUrls = null)
    {
        $this->maxUrls = $maxUrls;
    }

    public function create(FilesystemInterface $filesystem, SalesChannelContext $context, ?string $domain = null): SitemapHandleInterface
    {
        return new SitemapHandle($filesystem, $context, $domain, $this->maxUrls);
    }
}
