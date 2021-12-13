<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Facade\Traits\ContainerFactoryTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\DiscountTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsCountTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsGetTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsHasTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsRemoveTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\SurchargeTrait;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartFacade
{
    use DiscountTrait;
    use SurchargeTrait;
    use ItemsGetTrait;
    use ItemsRemoveTrait;
    use ItemsHasTrait;
    use ItemsCountTrait;
    use ContainerFactoryTrait;

    private Cart $cart;

    /**
     * @internal
     */
    public function __construct(CartFacadeHelper $helper, Cart $cart, SalesChannelContext $context)
    {
        $this->helper = $helper;
        $this->cart = $cart;
        $this->context = $context;
    }

    public function items(): ItemsFacade
    {
        return new ItemsFacade($this->cart->getLineItems(), $this->helper, $this->context);
    }

    public function products(): ProductsFacade
    {
        return new ProductsFacade($this->cart->getLineItems(), $this->helper, $this->context);
    }

    public function calculate(): void
    {
        $behavior = $this->cart->getBehavior();
        if (!$behavior) {
            throw new \LogicException('Cart behavior missing. The instanced cart was never calculated');
        }

        $this->cart = $behavior->disableHooks(function () use ($behavior) {
            return $this->helper->calculate($this->cart, $behavior, $this->context);
        });
    }

    public function price(): CartPriceFacade
    {
        return new CartPriceFacade($this->cart->getPrice(), $this->helper);
    }

    public function errors(): ErrorsFacade
    {
        return new ErrorsFacade($this->cart->getErrors());
    }

    private function getItems(): LineItemCollection
    {
        return $this->cart->getLineItems();
    }
}
