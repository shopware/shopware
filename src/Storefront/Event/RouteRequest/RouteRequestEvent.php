<?php declare(strict_types=1);

namespace Shopware\Storefront\Event\RouteRequest;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class RouteRequestEvent extends NestedEvent
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

    public function __construct(
        Request $storefrontRequest,
        Request $storeApiRequest,
        SalesChannelContext $salesChannelContext
    ) {
        $this->storefrontRequest = $storefrontRequest;
        $this->storeApiRequest = $storeApiRequest;
        $this->salesChannelContext = $salesChannelContext;
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
}
