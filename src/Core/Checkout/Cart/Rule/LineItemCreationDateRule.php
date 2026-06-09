<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
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
class LineItemCreationDateRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemCreationDate';

    /**
     * @internal
     *
     * @param string|array{from: string, to: string}|null $lineItemCreationDate
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected string|array|null $lineItemCreationDate = null
    ) {
        parent::__construct();
    }

    public function getConstraints(): array
    {
        $constraints = [
            'lineItemCreationDate' => RuleConstraints::datetime(),
            'operator' => RuleConstraints::datetimeOperators(emptyAllowed: false),
        ];

        if ($this->operator === self::OPERATOR_BETWEEN) {
            $constraints['lineItemCreationDate'] = RuleConstraints::dateBetween();
        }

        return $constraints;
    }

    public function match(RuleScope $scope): bool
    {
        $ruleValue = $this->lineItemCreationDate;

        if ($ruleValue === null) {
            return false;
        }

        if ($scope instanceof LineItemScope) {
            return $this->matchesCreationDate($scope->getLineItem(), $ruleValue);
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $lineItem) {
            if ($this->matchesCreationDate($lineItem, $ruleValue)) {
                return true;
            }
        }

        return false;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_DATE)
            ->dateTimeField('lineItemCreationDate');
    }

    /**
     * @param string|array{from: string, to: string} $ruleValue
     *
     * @throws CartException
     */
    private function matchesCreationDate(LineItem $lineItem, string|array $ruleValue): bool
    {
        /** @var string|null $itemCreatedString */
        $itemCreatedString = $lineItem->getPayloadValue('createdAt');

        if ($itemCreatedString === null) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        try {
            $itemCreated = new \DateTime($itemCreatedString);
        } catch (\Exception) {
            return false;
        }

        return RuleComparison::datetimeValue(
            $itemCreated,
            $ruleValue,
            $this->operator
        );
    }
}
