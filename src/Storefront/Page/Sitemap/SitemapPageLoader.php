<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Sitemap;

use Shopware\Core\Content\Sitemap\SalesChannel\AbstractSitemapRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

#[Package('sales-channel')]
class SitemapPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractSitemapRoute $sitemapRoute
    ) {
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function load(Request $request, SalesChannelContext $context): SitemapPage
    {
        $page = new SitemapPage();
        $page->setSitemaps($this->sitemapRoute->load($request, $context)->getSitemaps()->getElements());

        $this->eventDispatcher->dispatch(
            new SitemapPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
