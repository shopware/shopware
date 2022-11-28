<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Rule;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @package business-ops
 */
class FlowRuleScope extends CartRuleScope
{
    private OrderEntity $order;

    public function __construct(OrderEntity $order, Cart $cart, SalesChannelContext $context)
    {
        parent::__construct($cart, $context);
        $this->order = $order;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }
}
