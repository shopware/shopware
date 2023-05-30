<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Content\Product\SalesChannel\Listing\FilterCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
class ProductListingCollectFilterEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var SalesChannelContext
     */
    protected $context;

    /**
     * @var FilterCollection
     */
    protected $filters;

    public function __construct(
        Request $request,
        FilterCollection $filters,
        SalesChannelContext $context
    ) {
        $this->request = $request;
        $this->context = $context;
        $this->filters = $filters;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getFilters(): FilterCollection
    {
        return $this->filters;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }
}
