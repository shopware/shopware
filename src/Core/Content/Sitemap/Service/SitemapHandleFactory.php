<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Package('sales-channel')]
class SitemapHandleFactory implements SitemapHandleFactoryInterface
{
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @internal
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function create(FilesystemInterface $filesystem, SalesChannelContext $context, ?string $domain = null): SitemapHandleInterface
    {
        return new SitemapHandle($filesystem, $context, $this->eventDispatcher, $domain);
    }
}
