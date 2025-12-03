<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraint;

/**
 * @deprecated tag:v6.8.0 - Use \Shopware\Core\Checkout\Cart\Rule\LineItemProductTypeRule instead.
 */
#[Package('fundamentals@after-sales')]
class LineItemProductStatesRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemProductStates';

    protected string $productState;

    protected string $operator;

    public function match(RuleScope $scope): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'match', 'v6.8.0.0', 'LineItemProductTypeRule::match')
        );

        if ($scope instanceof LineItemScope) {
            return $this->lineItemMatches($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $lineItem) {
            if ($this->lineItemMatches($lineItem)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array<int, Constraint>>
     */
    public function getConstraints(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'getConstraints', 'v6.8.0.0', 'LineItemProductTypeRule::getConstraints')
        );

        return [
            'operator' => RuleConstraints::stringOperators(false),
            'productState' => RuleConstraints::choice([
                State::IS_PHYSICAL,
                State::IS_DOWNLOAD,
            ]),
        ];
    }

    public function getConfig(): RuleConfig
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'getConfig', 'v6.8.0.0', 'LineItemProductTypeRule::getConfig')
        );

        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->selectField('productState', [
                State::IS_PHYSICAL,
                State::IS_DOWNLOAD,
            ]);
    }

    private function lineItemMatches(LineItem $lineItem): bool
    {
        $states = [];

        Feature::callSilentIfInactive('v6.8.0.0', function () use (&$states, $lineItem): void {
            $states = $lineItem->getStates();
        });

        return RuleComparison::stringArray($this->productState, array_values($states), $this->operator);
    }
}
