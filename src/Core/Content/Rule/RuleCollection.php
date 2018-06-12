<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;

use Shopware\Core\Framework\ORM\EntityCollection;

class RuleCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\Content\Rule\RuleStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? RuleStruct
    {
        return parent::get($id);
    }

    public function current(): RuleStruct
    {
        return parent::current();
    }

    public function filterMatchingRules(CalculatedCart $cart, CheckoutContext $context)
    {
        return $this->filter(
            function (RuleStruct $rule) use ($cart, $context) {
                return $rule->getPayload()->match(new CartRuleScope($cart, $context))->matches();
            }
        );
    }

    public function sortByPriority(): void
    {
        $this->sort(function (RuleStruct $a, RuleStruct $b) {
            return $b->getPriority() <=> $a->getPriority();
        });
    }

    protected function getExpectedClass(): string
    {
        return RuleStruct::class;
    }
}
