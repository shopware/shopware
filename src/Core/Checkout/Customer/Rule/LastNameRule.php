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

    public function __construct(string $operator = self::OPERATOR_EQ, ?string $lastName = null)
    {
        parent::__construct();
        $this->operator = $operator;
        $this->lastName = $lastName;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return false;
        }

        switch ($this->operator) {
            case Rule::OPERATOR_EQ:
                return strcasecmp($this->lastName, $customer->getLastName()) === 0;

            case Rule::OPERATOR_NEQ:
                return strcasecmp($this->lastName, $customer->getLastName()) !== 0;

            case Rule::OPERATOR_EMPTY:
                return empty(trim($customer->getLastName()));

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => [
                new NotBlank(),
                new Choice([Rule::OPERATOR_EQ, Rule::OPERATOR_NEQ, Rule::OPERATOR_EMPTY]),
            ],
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['lastName'] = [new NotBlank(), new Type('string')];

        return $constraints;
    }

    public function getName(): string
    {
        return 'customerLastName';
    }
}
