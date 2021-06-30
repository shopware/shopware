<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
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

    public function __construct(string $operator = self::OPERATOR_EQ, ?array $customerGroupIds = null)
    {
        parent::__construct();
        $this->operator = $operator;
        $this->customerGroupIds = $customerGroupIds;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $id = $scope->getSalesChannelContext()->getCurrentCustomerGroup()->getId();

        switch ($this->operator) {
            case self::OPERATOR_EQ:

                return $id !== null && \in_array($id, $this->customerGroupIds, true);

            case self::OPERATOR_NEQ:

                return $id !== null && !\in_array($id, $this->customerGroupIds, true);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        return [
            'customerGroupIds' => [new NotBlank(), new ArrayOfUuid()],
            'operator' => [new NotBlank(), new Choice([Rule::OPERATOR_EQ, Rule::OPERATOR_NEQ])],
        ];
    }

    public function getName(): string
    {
        return 'customerCustomerGroup';
    }
}
