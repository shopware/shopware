<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Util\FloatComparator;
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

        $zipCode = trim($location->getZipCode());

        $compareZipCode = null;
        if ($this->zipCodes) {
            $compareZipCode = $this->zipCodes[0];
        }

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return \in_array($zipCode, $this->zipCodes, true);

            case self::OPERATOR_NEQ:
                return !\in_array($zipCode, $this->zipCodes, true);

            case self::OPERATOR_GTE:
                return is_numeric($zipCode) && is_numeric($compareZipCode) && FloatComparator::greaterThanOrEquals((float) $zipCode, (float) $compareZipCode);

            case self::OPERATOR_LTE:
                return is_numeric($zipCode) && is_numeric($compareZipCode) && FloatComparator::lessThanOrEquals((float) $zipCode, (float) $compareZipCode);

            case self::OPERATOR_GT:
                return is_numeric($zipCode) && is_numeric($compareZipCode) && FloatComparator::greaterThan((float) $zipCode, (float) $compareZipCode);

            case self::OPERATOR_LT:
                return is_numeric($zipCode) && is_numeric($compareZipCode) && FloatComparator::lessThan((float) $zipCode, (float) $compareZipCode);

            case self::OPERATOR_EMPTY:
                return empty($zipCode);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => [
                new NotBlank(),
                new Choice([
                    self::OPERATOR_EQ,
                    self::OPERATOR_NEQ,
                    self::OPERATOR_EMPTY,
                    self::OPERATOR_GTE,
                    self::OPERATOR_LTE,
                    self::OPERATOR_GT,
                    self::OPERATOR_LT,
                ]),
            ],
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['zipCodes'] = [new NotBlank(), new ArrayOfType('string')];

        return $constraints;
    }

    public function getName(): string
    {
        return 'customerShippingZipCode';
    }
}
