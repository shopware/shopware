<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class ShippingCountryRule extends Rule
{
    /**
     * @var string[]
     */
    protected $countryIds;

    /**
     * @var string
     */
    protected $operator;

    public function __construct()
    {
        $this->operator = self::OPERATOR_EQ;
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(RuleScope $scope): Match
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return new Match(false, ['Wrong scope']);
        }

        $context = $scope->getCheckoutContext();
        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return new Match(
                    \in_array($context->getShippingLocation()->getCountry()->getId(), $this->countryIds, true),
                    ['Shipping country id not matched']
                );

            case self::OPERATOR_NEQ:
                return new Match(
                    !\in_array($context->getShippingLocation()->getCountry()->getId(), $this->countryIds, true),
                    ['Shipping country id matched']
                );

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }

    public function getConstraints(): array
    {
        return [
            'countryIds' => [new NotBlank(), new ArrayOfUuid()],
            'operator' => [new Choice([self::OPERATOR_NEQ, self::OPERATOR_EQ])],
        ];
    }

    public function getName(): string
    {
        return 'customerShippingCountry';
    }
}
