<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Sitemap;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
class SitemapPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected SitemapPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request,
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): SitemapPage
    {
        return $this->page;
    }
}
