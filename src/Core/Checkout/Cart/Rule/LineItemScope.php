<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LineItemScope extends CheckoutRuleScope
{
    /**
     * @var LineItem
     */
    protected $lineItem;

    public function __construct(LineItem $lineItem, SalesChannelContext $context)
    {
        parent::__construct($context);
        $this->lineItem = $lineItem;
    }

    public function getLineItem(): LineItem
    {
        return $this->lineItem;
    }
}
