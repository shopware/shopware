<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
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
class CustomerBirthdayRule extends Rule
{
    final public const RULE_NAME = 'customerBirthday';

    /**
     * @internal
     *
     * @param string|array{from: string, to: string}|null $birthday
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected string|array|null $birthday = null
    ) {
        parent::__construct();
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::dateOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['birthday'] = RuleConstraints::date();

        if ($this->operator === self::OPERATOR_BETWEEN) {
            $constraints['birthday'] = RuleConstraints::dateBetween();
        }

        return $constraints;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        $customerBirthday = $customer->getBirthday();

        if ($customerBirthday instanceof \DateTimeImmutable) {
            $customerBirthday = \DateTime::createFromImmutable($customerBirthday);
        }

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $customerBirthday === null;
        }

        if (!$customerBirthday instanceof \DateTime || $this->birthday === null) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return RuleComparison::dateValue(
            $customerBirthday,
            $this->birthday,
            $this->operator
        );
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_DATE, true)
            ->dateField('birthday');
    }
}
