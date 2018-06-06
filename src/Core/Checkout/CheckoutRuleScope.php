<?php declare(strict_types=1);

namespace Shopware\Core\Checkout;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Rule\RuleScope;

class CheckoutRuleScope extends RuleScope
{
    /**
     * @var CheckoutContext
     */
    protected $context;

    public function __construct(CheckoutContext $context)
    {
        $this->context = $context;
    }

    public function getCheckoutContext(): CheckoutContext
    {
        return $this->context;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }
}
