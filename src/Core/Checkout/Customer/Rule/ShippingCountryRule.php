<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
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

    public function __construct(string $operator = self::OPERATOR_EQ, ?array $countryIds = null)
    {
        parent::__construct();
        $this->operator = $operator;
        $this->countryIds = $countryIds;
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $countryId = $scope->getSalesChannelContext()
            ->getShippingLocation()
            ->getCountry()
            ->getId();

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return \in_array($countryId, $this->countryIds, true);

            case self::OPERATOR_NEQ:
                return !\in_array($countryId, $this->countryIds, true);

            case self::OPERATOR_EMPTY:
                return empty($countryId);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => [
                new NotBlank(),
                new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ, self::OPERATOR_EMPTY]),
            ],
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['countryIds'] = [new NotBlank(), new ArrayOfUuid()];

        return $constraints;
    }

    public function getName(): string
    {
        return 'customerShippingCountry';
    }
}
