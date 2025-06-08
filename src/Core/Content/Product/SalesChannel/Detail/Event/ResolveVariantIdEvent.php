<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class ResolveVariantIdEvent extends Event implements ShopwareSalesChannelEvent
{
    public function __construct(
        private readonly string $productId,
        private ?string $resolvedVariantId,
        private readonly SalesChannelContext $salesChannelContext
    ) {
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setResolvedVariantId(?string $resolvedVariantId): void
    {
        $this->resolvedVariantId = $resolvedVariantId;
    }

    public function getResolvedVariantId(): ?string
    {
        return $this->resolvedVariantId;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
