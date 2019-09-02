<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SitemapWriterInterface
{
    public const SITEMAP_URL_LIMIT = 49999;

    public function writeFile(SalesChannelContext $salesChannelContext, array $urls = []): bool;

    public function closeFiles(): void;

    public function lock(SalesChannelContext $salesChannelContext): bool;

    public function unlock(SalesChannelContext $salesChannelContext): bool;
}
