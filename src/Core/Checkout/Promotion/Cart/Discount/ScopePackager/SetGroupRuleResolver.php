<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
readonly class SetGroupRuleResolver
{
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

        if ($rules === null || $rules === []) {
            return new RuleCollection();
        }

        if (!\is_array($rules)) {
            throw PromotionException::promotionSetGroupNotFound((string) ($group['groupId'] ?? ''));
        }

        $ruleIds = [];
        foreach ($rules as $rule) {
            if (!\is_array($rule) || !\is_string($rule['id'] ?? null)) {
                throw PromotionException::promotionSetGroupNotFound((string) ($group['groupId'] ?? ''));
            }

            $ruleIds[] = $rule['id'];
        }

        $loadedRules = $this->ruleLoader->load($context);
        $resolvedRules = new RuleCollection();

        foreach ($ruleIds as $ruleId) {
            if (($loadedRule = $loadedRules->get($ruleId)) === null) {
                throw PromotionException::promotionSetGroupNotFound((string) ($group['groupId'] ?? ''));
            }

            $resolvedRules->add($loadedRule);
        }

        return $resolvedRules;
    }
}
