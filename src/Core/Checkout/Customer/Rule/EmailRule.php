<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('business-ops')]
class EmailRule extends Rule
{
    final public const RULE_NAME = 'customerEmail';

    /**
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?string $email = null
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        if ($this->email && mb_strpos($this->email, '*') !== false) {
            return $this->matchPartially($customer);
        }

        return $this->matchExact($customer);
    }

    public function getConstraints(): array
    {
        return [
            'operator' => RuleConstraints::stringOperators(false),
            'email' => RuleConstraints::string(),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->stringField('email');
    }

    private function matchPartially(CustomerEntity $customer): bool
    {
        if ($this->email === null) {
            throw new UnsupportedValueException(\gettype($this->email), self::class);
        }

        $email = str_replace('\*', '(.*?)', preg_quote($this->email, '/'));
        $regex = sprintf('/^%s$/i', $email);

        return match ($this->operator) {
            Rule::OPERATOR_EQ => preg_match($regex, $customer->getEmail()) === 1,
            Rule::OPERATOR_NEQ => preg_match($regex, $customer->getEmail()) !== 1,
            default => throw new UnsupportedOperatorException($this->operator, self::class),
        };
    }

    private function matchExact(CustomerEntity $customer): bool
    {
        if ($this->email === null) {
            throw new UnsupportedValueException(\gettype($this->email), self::class);
        }

        return RuleComparison::string($customer->getEmail(), $this->email, $this->operator);
    }
}
