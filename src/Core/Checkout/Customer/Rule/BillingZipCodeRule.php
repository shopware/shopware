<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfType;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class BillingZipCodeRule extends Rule
{
    /**
     * @var string[]
     */
    protected $zipCodes;

    /**
     * @var string
     */
    protected $operator;

    public function __construct()
    {
        $this->operator = self::OPERATOR_EQ;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        /** @var CheckoutRuleScope $scope */
        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return false;
        }

        $zipCode = $customer->getActiveBillingAddress()->getZipcode();
        $this->zipCodes = array_map('strtolower', $this->zipCodes);

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return \in_array(strtolower($zipCode), $this->zipCodes, true);

            case self::OPERATOR_NEQ:
                return !\in_array(strtolower($zipCode), $this->zipCodes, true);

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }

    public function getConstraints(): array
    {
        return [
            'zipCodes' => [new NotBlank(), new ArrayOfType('string')],
            'operator' => [new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ])],
        ];
    }

    public function getName(): string
    {
        return 'customerBillingZipCode';
    }
}
