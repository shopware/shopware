<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event is dispatched when the data context hash of the cart products is built, the cart only loads the product data again if this hash has changed.
 * Listeners can add parts to the hash for every state outside the sales channel context that the product data or prices depend on.
 */
#[Package('inventory')]
class ProductCartDataContextHashEvent extends Event implements ShopwareSalesChannelEvent
{
    /**
     * @var array<string, string|int|float|bool|array<mixed>|null>
     */
    private array $parts = [];

    public function __construct(
        private readonly CartDataCollection $data,
        private readonly Cart $original,
        private readonly SalesChannelContext $salesChannelContext,
        private readonly CartBehavior $behavior,
    ) {
    }

    /**
     * @param string|int|float|bool|array<mixed>|null $value
     */
    public function add(string $name, string|int|float|bool|array|null $value): void
    {
        $this->parts[$name] = $value;
    }

    /**
     * @return array<string, string|int|float|bool|array<mixed>|null>
     */
    public function getParts(): array
    {
        $parts = $this->parts;
        ksort($parts);

        return $parts;
    }

    public function getData(): CartDataCollection
    {
        return $this->data;
    }

    public function getOriginalCart(): Cart
    {
        return $this->original;
    }

    public function getBehavior(): CartBehavior
    {
        return $this->behavior;
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
