<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Address\Detail;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class AddressDetailPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected AddressDetailPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): AddressDetailPage
    {
        return $this->page;
    }
}
