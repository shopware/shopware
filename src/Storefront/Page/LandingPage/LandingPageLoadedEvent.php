<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\LandingPage;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('buyers-experience')]
class LandingPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var LandingPage
     */
    protected $page;

    public function __construct(
        LandingPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): LandingPage
    {
        return $this->page;
    }
}
