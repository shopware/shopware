<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('business-ops')]
class CustomerBirthdayRule extends Rule
{
    final public const RULE_NAME = 'customerBirthday';

    /**
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?string $birthday = null
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

        $constraints['birthday'] = RuleConstraints::datetime();

        return $constraints;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if ($this->birthday === null && $this->operator !== self::OPERATOR_EMPTY) {
            throw new UnsupportedValueException(\gettype($this->birthday), self::class);
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

        if (
            !$customerBirthday instanceof \DateTime
            || !$this->birthday
            || \strtotime($this->birthday) === false
        ) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        $birthdayValue = new \DateTime($this->birthday);

        return RuleComparison::datetime($customerBirthday, $birthdayValue, $this->operator);
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER, true)
            ->dateTimeField('birthday');
    }
}
