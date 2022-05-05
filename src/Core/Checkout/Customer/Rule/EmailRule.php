<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

class EmailRule extends Rule
{
    protected ?string $email;

    protected string $operator;

    /**
     * @internal
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?string $email = null)
    {
        parent::__construct();
        $this->operator = $operator;
        $this->email = $email;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return false;
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

    public function getName(): string
    {
        return 'customerEmail';
    }

    private function matchPartially(CustomerEntity $customer): bool
    {
        if ($this->email === null) {
            throw new UnsupportedValueException(\gettype($this->email), self::class);
        }

        $email = str_replace('\*', '(.*?)', preg_quote($this->email, '/'));
        $regex = sprintf('/^%s$/i', $email);

        switch ($this->operator) {
            case Rule::OPERATOR_EQ:
                return preg_match($regex, $customer->getEmail()) === 1;

            case Rule::OPERATOR_NEQ:
                return preg_match($regex, $customer->getEmail()) !== 1;

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    private function matchExact(CustomerEntity $customer): bool
    {
        if ($this->email === null) {
            throw new UnsupportedValueException(\gettype($this->email), self::class);
        }

        return RuleComparison::string($customer->getEmail(), $this->email, $this->operator);
    }
}
