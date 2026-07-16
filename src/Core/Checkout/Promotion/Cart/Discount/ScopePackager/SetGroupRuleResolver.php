<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
readonly class SetGroupRuleResolver
{
    /**
     * @internal
     */
    public function __construct(private AbstractRuleLoader $ruleLoader)
    {
    }

    /**
     * @param array<string, mixed> $group
     */
    public function resolve(array $group, Context $context): RuleCollection
    {
        $rules = $group['rules'] ?? null;

        if ($rules instanceof RuleCollection) {
            return $rules;
        }

        if ($rules === null) {
            return new RuleCollection();
        }

        $ruleIds = array_column((array) $rules, 'id');

        if ($ruleIds === []) {
            return new RuleCollection();
        }

        $loaded = $this->ruleLoader->load($context)->filter(
            static fn (RuleEntity $rule): bool => \in_array($rule->getId(), $ruleIds, true),
        );

        if ($loaded->count() !== \count($ruleIds)) {
            throw PromotionException::promotionSetGroupNotFound((string) ($group['groupId'] ?? ''));
        }

        return new RuleCollection($loaded->getElements());
    }
}
