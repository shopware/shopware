<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('business-ops')]
class LineItemPropertyValueRule extends Rule
{
    public const RULE_NAME = 'cartLineItemPropertyValue';

    /**
     * @internal
     *
     * @param list<string>|null $identifiers
     */
    public function __construct(
        public string $operator = Rule::OPERATOR_EQ,
        public ?array $identifiers = null
    ) {
        parent::__construct();
    }

    public function getConstraints(): array
    {
        return [
            'operator' => RuleConstraints::uuidOperators(false),
            'identifiers' => RuleConstraints::uuids(),
        ];
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchLineItem($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $item) {
            if ($this->matchLineItem($item)) {
                return true;
            }
        }

        return false;
    }

    public function matchLineItem(LineItem $lineItem): bool
    {
        /**
         * @var list<string> $value
         */
        $value = $lineItem->getPayloadValue('propertyIds') ?? [];

        return RuleComparison::uuids(
            $value,
            $this->identifiers,
            $this->operator
        );
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->entitySelectField('identifiers', PropertyGroupOptionDefinition::ENTITY_NAME, true);
    }
}
