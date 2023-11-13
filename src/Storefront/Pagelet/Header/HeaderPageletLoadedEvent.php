<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
class HeaderPageletLoadedEvent extends PageletLoadedEvent
{
    /**
     * @var HeaderPagelet
     */
    protected $pagelet;

    public function __construct(
        HeaderPagelet $pagelet,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->pagelet = $pagelet;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPagelet(): HeaderPagelet
    {
        return $this->pagelet;
    }
}
