<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LastNameRule extends Rule
{
    /**
     * @var string
     */
    protected $lastName;

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

        switch ($this->operator) {
            case Rule::OPERATOR_EQ:
                return strcasecmp($this->lastName, $customer->getLastName()) === 0;

            case Rule::OPERATOR_NEQ:
                return strcasecmp($this->lastName, $customer->getLastName()) !== 0;

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }

    public function getConstraints(): array
    {
        return [
            'lastName' => [new NotBlank(), new Type('string')],
            'operator' => [new Choice([Rule::OPERATOR_EQ, Rule::OPERATOR_NEQ])],
        ];
    }

    public function getName(): string
    {
        return 'customerLastName';
    }
}
