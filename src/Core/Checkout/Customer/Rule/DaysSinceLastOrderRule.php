<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class DaysSinceLastOrderRule extends Rule
{
    protected string $operator = Rule::OPERATOR_EQ;

    protected ?int $daysPassed = null;

    public function getName(): string
    {
        return 'customerDaysSinceLastOrder';
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $currentDate = $scope->getCurrentTime()->setTime(0, 0, 0, 0);
        $customer = $scope->getSalesChannelContext()->getCustomer();

        if (!$customer) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        $lastOrderDate = $customer->getLastOrderDate();

        if ($lastOrderDate === null) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        if ($this->daysPassed === null) {
            return false;
        }

        if (method_exists($lastOrderDate, 'setTime')) {
            $lastOrderDate = $lastOrderDate->setTime(0, 0, 0, 0);
        }
        $interval = $lastOrderDate->diff($currentDate);

        if ($this->operator === self::OPERATOR_EMPTY) {
            return false;
        }

        return RuleComparison::numeric((int) $interval->days, $this->daysPassed, $this->operator);
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::numericOperators(),
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
