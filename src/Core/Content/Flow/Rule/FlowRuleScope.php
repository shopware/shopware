<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Rule;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('business-ops')]
class FlowRuleScope extends CartRuleScope
{
    public function __construct(
        private readonly OrderEntity $order,
        Cart $cart,
        SalesChannelContext $context
    ) {
        parent::__construct($cart, $context);
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }
}
