<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Document;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/*
 * @internal (flag:FEATURE_NEXT_10537)
 */
class DocumentPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var DocumentPage
     */
    protected $page;

    public function __construct(DocumentPage $page, SalesChannelContext $salesChannelContext, Request $request)
    {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): DocumentPage
    {
        return $this->page;
    }
}
