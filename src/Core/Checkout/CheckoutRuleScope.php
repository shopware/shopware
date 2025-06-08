<?php declare(strict_types=1);

namespace Shopware\Core\Checkout;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class CheckoutRuleScope extends RuleScope
{
    public function __construct(
        protected SalesChannelContext $context
    ) {
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }
}
