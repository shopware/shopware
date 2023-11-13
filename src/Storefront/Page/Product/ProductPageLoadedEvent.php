<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
class ProductPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var ProductPage
     */
    protected $page;

    public function __construct(
        ProductPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): ProductPage
    {
        return $this->page;
    }
}
