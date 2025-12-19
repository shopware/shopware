<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Breadcrumb;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class BreadcrumbPageletLoadedEvent extends PageletLoadedEvent
{
    public function __construct(
        protected BreadcrumbPagelet $pagelet,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPagelet(): BreadcrumbPagelet
    {
        return $this->pagelet;
    }
}
