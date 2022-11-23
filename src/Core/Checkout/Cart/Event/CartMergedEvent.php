<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package checkout
 */
class CartMergedEvent extends Event implements ShopwareSalesChannelEvent
{
    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var SalesChannelContext
     */
    protected $context;

    /**
     * @depretacted tag:6.5.0.0 - This will be required in the future
     **/
    protected ?Cart $previousCart;

    /**
     * @internal
     *
     * @deprecated tag:v6.5.0 - Parameter $previousCart will be required
     */
    public function __construct(Cart $cart, SalesChannelContext $context, ?Cart $previousCart = null)
    {
        $this->cart = $cart;
        $this->context = $context;

        if ($previousCart === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                \sprintf('"%s::%s()" will require the previous cart as required third parameter in v6.5.0.0', __CLASS__, __METHOD__)
            );
        }

        $this->previousCart = $previousCart;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getPreviousCart(): ?Cart
    {
        return $this->previousCart;
    }
}
