<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Suggest;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('services-settings')]
class SuggestPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var SuggestPage
     */
    protected $page;

    public function __construct(
        SuggestPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): SuggestPage
    {
        return $this->page;
    }
}
