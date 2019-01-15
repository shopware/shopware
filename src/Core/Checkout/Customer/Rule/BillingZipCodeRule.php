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

    public function match(RuleScope $scope): Match
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return new Match(false, ['Wrong scope']);
        }

        /** @var CheckoutRuleScope $scope */
        if (!$customer = $scope->getCheckoutContext()->getCustomer()) {
            return new Match(false, ['Not logged in customer']);
        }

        $zipCode = $customer->getActiveBillingAddress()->getZipcode();
        $this->zipCodes = array_map('strtolower', $this->zipCodes);

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return new Match(
                    \in_array(strtolower($zipCode), $this->zipCodes, true),
                    ['Zip code not matched']
                );
            case self::OPERATOR_NEQ:
                return new Match(
                    !\in_array(strtolower($zipCode), $this->zipCodes, true),
                    ['Zip code matched']
                );
            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }

    public static function getConstraints(): array
    {
        return [
            'zipCodes' => [new NotBlank(), new ArrayOfType('string')],
            'operator' => [new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ])],
        ];
    }

    public static function getName(): string
    {
        return 'billing_zip_code';
    }
}
