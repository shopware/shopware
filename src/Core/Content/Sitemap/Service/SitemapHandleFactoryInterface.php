<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SitemapHandleFactoryInterface
{
    public function create(FilesystemInterface $filesystem, SalesChannelContext $context): SitemapHandleInterface;
}
