<?php declare(strict_types=1);

namespace Shopware\Checkout\Rule\Specification\Scope;

use Shopware\Application\Context\Struct\StorefrontContext;
use Shopware\Checkout\Cart\LineItem\CalculatedLineItemInterface;

class CalculatedLineItemScope extends RuleScope
{
    /**
     * @var StorefrontContext
     */
    protected $context;

    /**
     * @var CalculatedLineItemInterface
     */
    protected $calculatedLineItem;

    public function __construct(CalculatedLineItemInterface $calculatedLineItem, StorefrontContext $context)
    {
        $this->calculatedLineItem = $calculatedLineItem;
        $this->context = $context;
    }

    public function getCalculatedLineItem(): CalculatedLineItemInterface
    {
        return $this->calculatedLineItem;
    }

    public function getContext(): StorefrontContext
    {
        return $this->context;
    }
}
