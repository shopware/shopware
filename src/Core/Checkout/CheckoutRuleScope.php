<?php declare(strict_types=1);

namespace Shopware\Core\Checkout;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Clock\NativeClock;

#[Package('checkout')]
class CheckoutRuleScope extends RuleScope
{
    // @TODO clock-bc: forwards to parent so readonly $clock is initialized; review BC
    public function __construct(
        protected SalesChannelContext $context,
        ClockInterface $clock = new NativeClock()
    ) {
        parent::__construct($clock);
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
