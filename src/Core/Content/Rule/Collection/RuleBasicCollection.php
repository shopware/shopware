<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Collection;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Content\Rule\Struct\RuleBasicStruct;
use Shopware\Core\Framework\ORM\EntityCollection;

class RuleBasicCollection extends EntityCollection
{
    /**
     * @var RuleBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? RuleBasicStruct
    {
        return parent::get($id);
    }

    public function current(): RuleBasicStruct
    {
        return parent::current();
    }

    public function filterMatchingRules(CalculatedCart $cart, CheckoutContext $context)
    {
        return $this->filter(
            function (RuleBasicStruct $rule) use ($cart, $context) {
                return $rule->getPayload()->match(new CartRuleScope($cart, $context))->matches();
            }
        );
    }

    public function sortByPriority(): void
    {
        $this->sort(function (RuleBasicStruct $a, RuleBasicStruct $b) {
            return $b->getPriority() <=> $a->getPriority();
        });
    }

    protected function getExpectedClass(): string
    {
        return RuleBasicStruct::class;
    }
}
