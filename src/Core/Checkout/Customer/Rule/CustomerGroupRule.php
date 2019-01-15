<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class CustomerGroupRule extends Rule
{
    /**
     * @var string[]
     */
    protected $customerGroupIds;

    /**
     * @var string
     */
    protected $operator;

    public function __construct()
    {
        $this->operator = self::OPERATOR_EQ;
    }

    public function match(RuleScope $scope): Match
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return new Match(false, ['Wrong scope']);
        }

        $id = $scope->getCheckoutContext()->getCurrentCustomerGroup()->getId();

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                /* @var CheckoutRuleScope $scope */
                return new Match(
                    $id !== null && \in_array($id, $this->customerGroupIds, true),
                    ['Current customer group not matched']
                );

            case self::OPERATOR_NEQ:
                /* @var CheckoutRuleScope $scope */
                return new Match(
                    $id !== null && !\in_array($id, $this->customerGroupIds, true),
                    ['Current customer group matched']
                );

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }

    public static function getConstraints(): array
    {
        return [
            'customerGroupIds' => [new NotBlank(), new ArrayOfUuid()],
            'operator' => [new Choice([Rule::OPERATOR_EQ, Rule::OPERATOR_NEQ])],
        ];
    }

    public static function getName(): string
    {
        return 'customer_group';
    }
}
