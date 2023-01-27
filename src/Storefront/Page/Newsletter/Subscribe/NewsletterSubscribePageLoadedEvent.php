<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Newsletter\Subscribe;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('customer-order')]
class NewsletterSubscribePageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var NewsletterSubscribePage
     */
    protected $page;

    public function __construct(
        NewsletterSubscribePage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): NewsletterSubscribePage
    {
        return $this->page;
    }
}
