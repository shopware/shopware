<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class ShippingStreetRule extends Rule
{
    /**
     * @var string
     */
    protected $streetName;

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

        $value = strtolower($this->streetName);

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return new Match(
                    (bool) preg_match("/$value/", strtolower($location->getStreet())),
                    ['Shipping street not matched']
                );
            case self::OPERATOR_NEQ:
                return new Match(
                    !preg_match("/$value/", strtolower($location->getStreet())),
                    ['Shipping street matched']
                );
            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }

    public function getConstraints(): array
    {
        return [
            'streetName' => [new NotBlank(), new Type('string')],
            'operator' => [new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ])],
        ];
    }

    public function getName(): string
    {
        return 'swShippingStreet';
    }
}
