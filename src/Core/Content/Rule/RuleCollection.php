<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @extends EntityCollection<RuleEntity>
 */
class RuleCollection extends EntityCollection
{
    public function filterMatchingRules(Cart $cart, SalesChannelContext $context)
    {
        return $this->filter(
            function (RuleEntity $rule) use ($cart, $context) {
                return $rule->getPayload()->match(new CartRuleScope($cart, $context));
            }
        );
    }

    public function sortByPriority(): void
    {
        $this->sort(function (RuleEntity $a, RuleEntity $b) {
            return $b->getPriority() <=> $a->getPriority();
        });
    }

    public function equals(RuleCollection $rules): bool
    {
        if ($this->count() !== $rules->count()) {
            return false;
        }

        foreach ($this->elements as $element) {
            if (!$rules->has($element->getId())) {
                return false;
            }
        }

        return true;
    }

    public function getApiAlias(): string
    {
        return 'rule_collection';
    }

    protected function getExpectedClass(): string
    {
        return RuleEntity::class;
    }
}
