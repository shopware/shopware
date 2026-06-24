<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class LineItemPropertyRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemProperty';

    /**
     * @param list<string> $identifiers
     *
     * @internal
     */
    public function __construct(
        protected array $identifiers = [],
        protected string $operator = self::OPERATOR_EQ
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->lineItemMatch($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $lineItem) {
            if ($this->lineItemMatch($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'identifiers' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->entitySelectField('identifiers', PropertyGroupDefinition::ENTITY_NAME, true);
    }

    private function lineItemMatch(LineItem $lineItem): bool
    {
        $properties = $lineItem->getPayloadValue('propertyIds') ?? [];
        $options = $lineItem->getPayloadValue('optionIds') ?? [];

        /** @var list<string> $ids */
        $ids = array_merge($properties, $options);

        return RuleComparison::uuids($ids, $this->identifiers, $this->operator);
    }
}
