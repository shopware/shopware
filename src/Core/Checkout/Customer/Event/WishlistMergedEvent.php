<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('customer-order')]
class WishlistMergedEvent extends Event implements ShopwareSalesChannelEvent
{
    /**
     * @var array
     */
    protected $products;

    /**
     * @var SalesChannelContext
     */
    protected $context;

    public function __construct(
        array $product,
        SalesChannelContext $context
    ) {
        $this->products = $product;
        $this->context = $context;
    }

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
