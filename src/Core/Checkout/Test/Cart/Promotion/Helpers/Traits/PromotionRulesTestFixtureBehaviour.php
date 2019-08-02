<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits;

use Shopware\Core\Checkout\Cart\Rule\LineItemUnitPriceRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemWithQuantityRule;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;

trait PromotionRulesTestFixtureBehaviour
{
    /**
     * Build a rule entity with the provided rule
     * inside the payload property.
     */
    private function buildRuleEntity(Rule $rule): RuleEntity
    {
        $rules = new AndRule(
            [
                $rule,
            ]
        );

        $ruleEntity = new RuleEntity();
        $ruleEntity->setId(Uuid::randomHex());
        $ruleEntity->setPayload($rules);

        return $ruleEntity;
    }

    /**
     * Gets a minimum price rule with the provided price value.
     */
    private function getMinPriceRule(float $minPrice): LineItemUnitPriceRule
    {
        $rule = new LineItemUnitPriceRule();
        $rule->assign(['amount' => $minPrice, 'operator' => LineItemUnitPriceRule::OPERATOR_GTE]);

        return $rule;
    }

    /**
     * Gets a minimum quantity rule for the provided line item Id.
     */
    private function getMinQuantityRule(string $itemID, int $quantity): LineItemWithQuantityRule
    {
        $rule = new LineItemWithQuantityRule();
        $rule->assign(['id' => $itemID, 'quantity' => $quantity, 'operator' => LineItemWithQuantityRule::OPERATOR_GTE]);

        return $rule;
    }
}
