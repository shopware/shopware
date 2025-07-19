<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class WishlistMergedEvent extends Event implements ShopwareSalesChannelEvent
{
    /**
     * @param array<array{id: string, productId?: string, productVersionId?: string}> $products
     */
    public function __construct(
        protected array $products,
        protected SalesChannelContext $context
    ) {
    }

    /**
     * @return array<array{id: string, productId?: string, productVersionId?: string}>
     */
    public function getProducts(): array
    {
        return $this->products;
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
