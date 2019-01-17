<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Match;
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

    public function __construct()
    {
        $this->operator = self::OPERATOR_EQ;
    }

    public function match(RuleScope $scope): Match
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return new Match(false, ['Wrong scope']);
        }

        /** @var CheckoutRuleScope $scope */
        if (!$location = $scope->getCheckoutContext()->getShippingLocation()->getAddress()) {
            return new Match(
                false,
                ['Shipping location has no address']
            );
        }

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return new Match(
                    \in_array($location->getZipcode(), $this->zipCodes, true),
                    ['Shipping zip code not matched']
                );
            case self::OPERATOR_NEQ:
                return new Match(
                    !\in_array($location->getZipcode(), $this->zipCodes, true),
                    ['Shipping zip code matched']
                );
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
        return 'customerShippingZipCode';
    }
}
