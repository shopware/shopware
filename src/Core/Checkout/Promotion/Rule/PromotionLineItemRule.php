<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('business-ops')]
class PromotionLineItemRule extends Rule
{
    final public const RULE_NAME = 'promotionLineItem';

    /**
     * @internal
     *
     * @param list<string>|null $identifiers
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?array $identifiers = null
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->lineItemMatches($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $promotionLineItems = $scope->getCart()->getLineItems()->filterFlatByType(LineItem::PROMOTION_LINE_ITEM_TYPE);
        $hasNoPromotionLineItems = \count($promotionLineItems) === 0;

        if ($hasNoPromotionLineItems) {
            return $this->operator === self::OPERATOR_NEQ;
        }

        foreach ($promotionLineItems as $lineItem) {
            if ($lineItem->getPayloadValue('promotionId') === null) {
                continue;
            }

            if ($this->lineItemMatches($lineItem)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string>|null
     */
    public function getIdentifiers(): ?array
    {
        return $this->identifiers;
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
            ->entitySelectField('identifiers', PromotionDefinition::ENTITY_NAME, true);
    }

    private function lineItemMatches(LineItem $lineItem): bool
    {
        if ($lineItem->getType() !== LineItem::PROMOTION_LINE_ITEM_TYPE) {
            return $this->operator === self::OPERATOR_NEQ;
        }

        $promotionId = $lineItem->getPayloadValue('promotionId');

        return RuleComparison::uuids([$promotionId], $this->identifiers, $this->operator);
    }
}
