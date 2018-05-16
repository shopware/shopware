<?php declare(strict_types=1);

namespace Shopware\Application\Context\Rule\MatchContext;

use Shopware\Checkout\Cart\LineItem\CalculatedLineItemInterface;
use Shopware\Application\Context\Struct\StorefrontContext;

class CalculatedLineItemMatchContext extends RuleMatchContext
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
