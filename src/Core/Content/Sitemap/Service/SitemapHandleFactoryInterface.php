<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('services-settings')]
interface SitemapHandleFactoryInterface
{
    /**
     * @deprecated tag:v6.7.0 - reason:new-optional-parameter - Parameter ?string $domainId = null will be added
     */
    public function create(
        FilesystemOperator $filesystem,
        SalesChannelContext $context,
        ?string $domain = null,
        /* , ?string $domainId = null */
    ): SitemapHandleInterface;
}
