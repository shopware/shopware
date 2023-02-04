<?php declare(strict_types=1);

namespace Shopware\Storefront\Page;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
class GenericPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var Page
     */
    protected $page;

    public function __construct(
        Page $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): Page
    {
        return $this->page;
    }
}
