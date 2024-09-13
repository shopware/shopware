<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('services-settings')]
class SearchPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var SearchPage
     */
    protected $page;

    public function __construct(
        SearchPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): SearchPage
    {
        return $this->page;
    }
}
