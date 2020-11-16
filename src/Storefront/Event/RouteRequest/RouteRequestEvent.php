<?php declare(strict_types=1);

namespace Shopware\Storefront\Event\RouteRequest;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class RouteRequestEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    /**
     * @var Request
     */
    private $storefrontRequest;

    /**
     * @var Request
     */
    private $storeApiRequest;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var Criteria
     */
    private $criteria;

    public function __construct(
        Request $storefrontRequest,
        Request $storeApiRequest,
        SalesChannelContext $salesChannelContext,
        ?Criteria $criteria = null
    ) {
        $this->storefrontRequest = $storefrontRequest;
        $this->storeApiRequest = $storeApiRequest;
        $this->salesChannelContext = $salesChannelContext;
        $this->criteria = $criteria ?? new Criteria();
    }

    public function getStorefrontRequest(): Request
    {
        return $this->storefrontRequest;
    }

    public function getStoreApiRequest(): Request
    {
        return $this->storeApiRequest;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }
}
