<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('business-ops')]
class LineItemScope extends CheckoutRuleScope
{
    public function __construct(
        protected LineItem $lineItem,
        SalesChannelContext $context
    ) {
        parent::__construct($context);
    }

    public function getLineItem(): LineItem
    {
        return $this->lineItem;
    }
}
