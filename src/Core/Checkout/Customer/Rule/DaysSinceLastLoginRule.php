<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class DaysSinceLastLoginRule extends Rule
{
    protected string $operator = Rule::OPERATOR_EQ;

    protected ?int $daysPassed = null;

    public function getName(): string
    {
        return 'customerDaysSinceLastLogin';
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $currentDate = $scope->getCurrentTime()->setTime(0, 0, 0, 0);
        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        if ($this->daysPassed === null && $this->operator !== self::OPERATOR_EMPTY) {
            throw new UnsupportedValueException(\gettype($this->daysPassed), self::class);
        }

        if (!$customerLastLogin = $customer->getLastLogin()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        if ($this->daysPassed === null) {
            return false;
        }

        if (method_exists($customerLastLogin, 'setTime')) {
            $customerLastLogin = $customerLastLogin->setTime(0, 0, 0, 0);
        }
        $interval = $customerLastLogin->diff($currentDate);

        if ($this->operator === self::OPERATOR_EMPTY) {
            return false;
        }

        return RuleComparison::numeric((int) $interval->days, $this->daysPassed, $this->operator);
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::numericOperators(true),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['daysPassed'] = RuleConstraints::int();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER, true)
            ->intField('daysPassed');
    }
}
