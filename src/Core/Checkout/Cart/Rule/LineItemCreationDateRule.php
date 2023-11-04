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

#[Package('business-ops')]
class LineItemCreationDateRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemCreationDate';

    /**
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?string $lineItemCreationDate = null
    ) {
        parent::__construct();
    }

    public function getConstraints(): array
    {
        return [
            'lineItemCreationDate' => RuleConstraints::datetime(),
            'operator' => RuleConstraints::datetimeOperators(false),
        ];
    }

    public function match(RuleScope $scope): bool
    {
        if ($this->lineItemCreationDate === null) {
            return false;
        }

        try {
            $ruleValue = $this->buildDate($this->lineItemCreationDate);
        } catch (\Exception) {
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
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER)
            ->dateTimeField('lineItemCreationDate');
    }

    /**
     * @throws CartException
     */
    private function matchesCreationDate(LineItem $lineItem, \DateTime $ruleValue): bool
    {
        try {
            /** @var string|null $itemCreatedString */
            $itemCreatedString = $lineItem->getPayloadValue('createdAt');

            if ($itemCreatedString === null) {
                return RuleComparison::isNegativeOperator($this->operator);
            }

            $itemCreated = $this->buildDate($itemCreatedString);
        } catch (\Exception) {
            return false;
        }

        return RuleComparison::datetime($itemCreated, $ruleValue, $this->operator);
    }

    /**
     * @throws \Exception
     */
    private function buildDate(string $dateString): \DateTime
    {
        $dateTime = new \DateTime($dateString);

        return $dateTime;
    }
}
