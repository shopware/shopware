<?php declare(strict_types=1);

namespace Shopware\Application\Context\Rule\MatchContext;

use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Application\Context\Struct\StorefrontContext;

class CartRuleMatchContext extends RuleMatchContext
{
    /**
     * @var StorefrontContext
     */
    protected $context;

    /**
     * @var CalculatedCart
     */
    protected $calculatedCart;

    public function __construct(CalculatedCart $calculatedCart, StorefrontContext $context)
    {
        $this->context = $context;
        $this->calculatedCart = $calculatedCart;
    }

    public function getCalculatedCart(): CalculatedCart
    {
        return $this->calculatedCart;
    }

    public function getContext(): StorefrontContext
    {
        return $this->context;
    }
}
