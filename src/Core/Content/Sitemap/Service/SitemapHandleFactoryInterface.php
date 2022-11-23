<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @package sales-channel
 */
interface SitemapHandleFactoryInterface
{
    public function create(FilesystemOperator $filesystem, SalesChannelContext $context, ?string $domain = null): SitemapHandleInterface;
}
