<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Newsletter\Account;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('customer-order')]
class NewsletterAccountPageletLoadedEvent extends PageletLoadedEvent
{
    public function __construct(
        protected NewsletterAccountPagelet $pagelet,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPagelet(): NewsletterAccountPagelet
    {
        return $this->pagelet;
    }
}
