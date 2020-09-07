<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfType;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class ShippingZipCodeRule extends Rule
{
    /**
     * @var string[]
     */
    protected $zipCodes;

    /**
     * @var string
     */
    protected $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, ?array $zipCodes = null)
    {
        parent::__construct();
        $this->operator = $operator;
        $this->zipCodes = $zipCodes;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$location = $scope->getSalesChannelContext()->getShippingLocation()->getAddress()) {
            return false;
        }

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return \in_array($location->getZipcode(), $this->zipCodes, true);

            case self::OPERATOR_NEQ:
                return !\in_array($location->getZipcode(), $this->zipCodes, true);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        return [
            'zipCodes' => [new NotBlank(), new ArrayOfType('string')],
            'operator' => [new NotBlank(), new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ])],
        ];
    }

    public function getName(): string
    {
        return 'customerShippingZipCode';
    }
}
