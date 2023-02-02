<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Content\Rule\RuleCollection;

class RuleLoaderResult
{
    /**
     * @var Cart
     */
    private $cart;

    /**
     * @var RuleCollection
     */
    private $matchingRules;

    public function __construct(Cart $cart, RuleCollection $rules)
    {
        $this->cart = $cart;
        $this->matchingRules = $rules;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getMatchingRules(): RuleCollection
    {
        return $this->matchingRules;
    }
}
