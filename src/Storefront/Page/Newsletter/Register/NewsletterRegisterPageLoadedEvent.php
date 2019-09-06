<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Newsletter\Register;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class NewsletterRegisterPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var NewsletterRegisterPage
     */
    protected $page;

    public function __construct(NewsletterRegisterPage $page, SalesChannelContext $salesChannelContext, Request $request)
    {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): NewsletterRegisterPage
    {
        return $this->page;
    }
}
