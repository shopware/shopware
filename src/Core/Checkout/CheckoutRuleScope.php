<?php declare(strict_types=1);

namespace Shopware\Core\Checkout;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CheckoutRuleScope extends RuleScope
{
    /**
     * @var SalesChannelContext
     */
    protected $context;

    public function __construct(SalesChannelContext $context)
    {
        $this->context = $context;
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
