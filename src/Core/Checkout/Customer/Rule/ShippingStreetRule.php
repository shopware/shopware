<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
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

    public function __construct(string $operator = self::OPERATOR_EQ, ?string $streetName = null)
    {
        parent::__construct();
        $this->operator = $operator;
        $this->streetName = $streetName;
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
                return strcasecmp($this->streetName, $location->getStreet()) === 0;

            case self::OPERATOR_NEQ:
                return strcasecmp($this->streetName, $location->getStreet()) !== 0;

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        return [
            'streetName' => [new NotBlank(), new Type('string')],
            'operator' => [new NotBlank(), new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ])],
        ];
    }

    public function getName(): string
    {
        return 'customerShippingStreet';
    }
}
