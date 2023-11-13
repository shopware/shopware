<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Country;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
class CountryStateDataPageletLoadedEvent extends PageletLoadedEvent
{
    public function __construct(
        protected CountryStateDataPagelet $pagelet,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPagelet(): CountryStateDataPagelet
    {
        return $this->pagelet;
    }
}
