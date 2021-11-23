<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Hook;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Facade\CartFacadeHookFactory;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartHook extends Hook implements CartAware
{
    private Cart $cart;

    private SalesChannelContext $salesChannelContext;

    public function __construct(Cart $cart, SalesChannelContext $context)
    {
        parent::__construct($context->getContext());
        $this->cart = $cart;
        $this->salesChannelContext = $context;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getServiceIds(): array
    {
        return [
            CartFacadeHookFactory::class,
        ];
    }

    public function getName(): string
    {
        return 'cart';
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
