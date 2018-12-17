<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class RuleCollection extends EntityCollection
{
    /**
     * @var RuleEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? RuleEntity
    {
        return parent::get($id);
    }

    public function current(): RuleEntity
    {
        return parent::current();
    }

    public function filterMatchingRules(Cart $cart, CheckoutContext $context)
    {
        return $this->filter(
            function (RuleEntity $rule) use ($cart, $context) {
                return $rule->getPayload()->match(new CartRuleScope($cart, $context))->matches();
            }
        );
    }

    public function sortByPriority(): void
    {
        $this->sort(function (RuleEntity $a, RuleEntity $b) {
            return $b->getPriority() <=> $a->getPriority();
        });
    }

    protected function getExpectedClass(): string
    {
        return RuleEntity::class;
    }
}
