<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('storefront')]
class SwitchBuyBoxVariantEvent extends Event implements ShopwareSalesChannelEvent
{
    public function __construct(
        private readonly string $elementId,
        private readonly SalesChannelProductEntity $product,
        private readonly ?PropertyGroupCollection $configurator,
        private readonly Request $request,
        private readonly SalesChannelContext $salesChannelContext
    ) {
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
