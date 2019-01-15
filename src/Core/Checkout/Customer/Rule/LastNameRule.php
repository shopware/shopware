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

    public function match(RuleScope $scope): Match
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return new Match(false, ['Wrong scope']);
        }

        /** @var CheckoutRuleScope $scope */
        if (!$customer = $scope->getCheckoutContext()->getCustomer()) {
            return new Match(false, ['Not logged in customer']);
        }

        switch ($this->operator) {
            case Rule::OPERATOR_EQ:
                return new Match(
                    (bool) preg_match("/$this->lastName/", strtolower($customer->getLastName())),
                    ['Last name not matched']
                );
            case Rule::OPERATOR_NEQ:
                return new Match(
                    !(bool) preg_match("/$this->lastName/", strtolower($customer->getLastName())),
                    ['Last name matched']
                );
            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }

    public static function getConstraints(): array
    {
        return [
            'lastName' => [new NotBlank(), new Type('string')],
            'operator' => [new Choice([Rule::OPERATOR_EQ, Rule::OPERATOR_NEQ])],
        ];
    }

    public static function getName(): string
    {
        return 'last_name';
    }
}
