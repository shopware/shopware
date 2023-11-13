<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Navigation\Error;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
class ErrorPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var ErrorPage
     */
    protected $page;

    public function __construct(
        ErrorPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): ErrorPage
    {
        return $this->page;
    }
}
