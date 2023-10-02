<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('storefront')]
class CacheResponseSystemStatesEvent extends Event
{
    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
        private readonly Request $request,
        private readonly Cart $cart,
        private array $states
    ) {
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getStates(): array
    {
        return array_keys($this->states);
    }

    public function setState(string $key, bool $match): void
    {
        if ($match) {
            $this->states[$key] = true;

            return;
        }

        unset($this->states[$key]);
    }
}
