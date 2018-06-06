<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Specification\Scope;

use Shopware\Core\Checkout\CustomerContext;
use Shopware\Core\Checkout\Cart\LineItem\CalculatedLineItemInterface;

class CalculatedLineItemScope extends RuleScope
{
    /**
     * @var CustomerContext
     */
    protected $context;

    /**
     * @var CalculatedLineItemInterface
     */
    protected $calculatedLineItem;

    public function __construct(CalculatedLineItemInterface $calculatedLineItem, CustomerContext $context)
    {
        $this->calculatedLineItem = $calculatedLineItem;
        $this->context = $context;
    }

    public function getCalculatedLineItem(): CalculatedLineItemInterface
    {
        return $this->calculatedLineItem;
    }

    public function getContext(): CustomerContext
    {
        return $this->context;
    }
}
