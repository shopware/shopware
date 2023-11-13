<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Package('sales-channel')]
class SitemapHandleFactory implements SitemapHandleFactoryInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function create(FilesystemOperator $filesystem, SalesChannelContext $context, ?string $domain = null): SitemapHandleInterface
    {
        return new SitemapHandle($filesystem, $context, $this->eventDispatcher, $domain);
    }
}
