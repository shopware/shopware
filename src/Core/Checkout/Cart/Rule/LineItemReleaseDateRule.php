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
class LineItemReleaseDateRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemReleaseDate';

    /**
     * @internal
     *
     * @param string|array{from: string, to: string}|null $lineItemReleaseDate
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected string|array|null $lineItemReleaseDate = null
    ) {
        parent::__construct();
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::datetimeOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['lineItemReleaseDate'] = RuleConstraints::datetime();

        if ($this->operator === self::OPERATOR_BETWEEN) {
            $constraints['lineItemReleaseDate'] = RuleConstraints::dateBetween();
        }

        return $constraints;
    }

    public function match(RuleScope $scope): bool
    {
        $ruleValue = $this->lineItemReleaseDate;

        if ($ruleValue === null) {
            return false;
        }

        if ($scope instanceof LineItemScope) {
            return $this->matchesReleaseDate($scope->getLineItem(), $ruleValue);
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $lineItem) {
            if ($this->matchesReleaseDate($lineItem, $ruleValue)) {
                return true;
            }
        }

        return false;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_DATE, true)
            ->dateTimeField('lineItemReleaseDate');
    }

    /**
     * @param string|array{from: string, to: string} $ruleValue
     *
     * @throws CartException
     */
    private function matchesReleaseDate(LineItem $lineItem, string|array $ruleValue): bool
    {
        /** @var string|null $releasedAtString */
        $releasedAtString = $lineItem->getPayloadValue('releaseDate');

        if ($releasedAtString === null) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        try {
            $itemReleased = new \DateTime($releasedAtString);
        } catch (\Exception) {
            return false;
        }

        return RuleComparison::datetimeValue(
            $itemReleased,
            $ruleValue,
            $this->operator
        );
    }
}
