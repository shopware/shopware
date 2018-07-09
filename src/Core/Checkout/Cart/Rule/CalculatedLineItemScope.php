<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\CheckoutRuleScope;

class CalculatedLineItemScope extends CheckoutRuleScope
{
    /**
     * @var LineItem
     */
    protected $lineItem;

    public function __construct(LineItem $calculatedLineItem, CheckoutContext $context)
    {
        parent::__construct($context);
        $this->lineItem = $calculatedLineItem;
    }

    public function getLineItem(): LineItem
    {
        return $this->lineItem;
    }
}
