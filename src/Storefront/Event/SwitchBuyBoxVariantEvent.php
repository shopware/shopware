<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class SwitchBuyBoxVariantEvent extends Event implements ShopwareSalesChannelEvent
{
    private Request $request;

    private string $elementId;

    private SalesChannelProductEntity $product;

    private ?PropertyGroupCollection $configurator;

    private SalesChannelContext $salesChannelContext;

    public function __construct(
        string $elementId,
        SalesChannelProductEntity $product,
        ?PropertyGroupCollection $configurator,
        Request $request,
        SalesChannelContext $salesChannelContext
    ) {
        $this->request = $request;
        $this->elementId = $elementId;
        $this->product = $product;
        $this->configurator = $configurator;
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getElementId(): string
    {
        return $this->elementId;
    }

    public function getProduct(): SalesChannelProductEntity
    {
        return $this->product;
    }

    public function getConfigurator(): ?PropertyGroupCollection
    {
        return $this->configurator;
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
