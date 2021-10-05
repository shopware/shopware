<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Container;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Util\FloatComparator;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfType;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

abstract class ZipCodeRule extends Rule
{
    protected string $operator;

    /**
     * @var string[]|null
     */
    protected ?array $zipCodes;

    public function __construct(string $operator = self::OPERATOR_EQ, ?array $zipCodes = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->zipCodes = $zipCodes;
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

    protected function matchZipCode(CustomerAddressEntity $address): bool
    {
        $zipCode = $this->sanitizeZipCode($address);

        if ($this->zipCodes === null) {
            throw new UnsupportedValueException(\gettype($this->zipCodes), self::class);
        }

        $this->zipCodes = array_map('mb_strtolower', $this->zipCodes);
        $compareZipCode = $this->zipCodes[0];

        switch ($this->operator) {
            case Rule::OPERATOR_EQ:
                return !empty($this->getMatches($zipCode));

            case Rule::OPERATOR_NEQ:
                return empty($this->getMatches($zipCode));

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

    private function getMatches(string $zipCode): array
    {
        $zipCode = str_replace('\*', '(.*?)', preg_quote($zipCode, '/'));
        $regex = sprintf('/^%s$/i', $zipCode);

        return array_filter((array) $this->zipCodes, function (string $zipCodeMatch) use ($regex) {
            return preg_match($regex, $zipCodeMatch) === 1;
        });
    }

    private function sanitizeZipCode(CustomerAddressEntity $address): string
    {
        $zipCode = trim($address->getZipcode());

        if (\in_array($this->operator, [self::OPERATOR_EQ, self::OPERATOR_NEQ], true)) {
            return $zipCode;
        }

        // Japanese post codes are separated by dashes but otherwise numeric, replace dashes for numeric expressions
        if ($address->getCountry() && $address->getCountry()->getIso3() === 'JPN') {
            $zipCode = str_replace('-', '', $zipCode);
        }

        return $zipCode;
    }
}
