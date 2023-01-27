<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Package('business-ops')]
class CustomerCustomFieldRule extends Rule
{
    final public const RULE_NAME = 'customerCustomField';

    protected string|int|bool|null|float $renderedFieldValue = null;

    /**
     * @param array<string, string> $renderedField
     *
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected array $renderedField = []
    ) {
        parent::__construct();
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        return $this->isCustomFieldValid(
            $scope->getSalesChannelContext()->getCustomer()
        );
    }

    public function getConstraints(): array
    {
        return [
            'renderedField' => [new NotBlank()],
            'selectedField' => [new NotBlank()],
            'selectedFieldSet' => [new NotBlank()],
            'renderedFieldValue' => $this->getRenderedFieldValueConstraints(),
            'operator' => [
                new NotBlank(),
                new Choice(
                    [
                        self::OPERATOR_NEQ,
                        self::OPERATOR_GTE,
                        self::OPERATOR_LTE,
                        self::OPERATOR_EQ,
                        self::OPERATOR_GT,
                        self::OPERATOR_LT,
                    ]
                ),
            ],
        ];
    }

    /**
     * @throws UnsupportedOperatorException
     */
    private function isCustomFieldValid(?CustomerEntity $customer): bool
    {
        if ($customer === null) {
            return false;
        }
        $customerFields = $customer->getCustomFields() ?? [];

        $actual = $this->getValue($customerFields, $this->renderedField);
        $expected = $this->getExpectedValue($this->renderedFieldValue, $this->renderedField);

        if ($actual === null) {
            if ($this->operator === self::OPERATOR_NEQ) {
                return $actual !== $expected;
            }

            return false;
        }

        return match ($this->operator) {
            self::OPERATOR_NEQ => $actual !== $expected,
            self::OPERATOR_GTE => $actual >= $expected,
            self::OPERATOR_LTE => $actual <= $expected,
            self::OPERATOR_EQ => $actual === $expected,
            self::OPERATOR_GT => $actual > $expected,
            self::OPERATOR_LT => $actual < $expected,
            default => throw new UnsupportedOperatorException($this->operator, self::class),
        };
    }

    /**
     * @return Constraint[]
     */
    private function getRenderedFieldValueConstraints(): array
    {
        $constraints = [];

        if (!\is_array($this->renderedField) || !\array_key_exists('type', $this->renderedField)) {
            return [new NotBlank()];
        }

        if ($this->renderedField['type'] !== CustomFieldTypes::BOOL) {
            $constraints[] = new NotBlank();
        }

        return $constraints;
    }

    /**
     * @param array<string, mixed> $customFields
     * @param array<string, string> $renderedField
     */
    private function getValue(array $customFields, array $renderedField): float|bool|int|string|null
    {
        if ($this->isSwitchOrBoolField($renderedField)) {
            if (!empty($customFields) && \array_key_exists($this->renderedField['name'], $customFields)) {
                return $customFields[$renderedField['name']];
            }

            return false;
        }

        if (!empty($customFields) && \array_key_exists($this->renderedField['name'], $customFields)) {
            return $customFields[$renderedField['name']];
        }

        return null;
    }

    /**
     * @param string|int|float|bool|null $renderedFieldValue
     * @param array<string, string> $renderedField
     */
    private function getExpectedValue($renderedFieldValue, array $renderedField): float|bool|int|string|null
    {
        if ($this->isSwitchOrBoolField($renderedField) && \is_string($renderedFieldValue)) {
            return filter_var($renderedFieldValue, \FILTER_VALIDATE_BOOLEAN);
        }
        if ($this->isSwitchOrBoolField($renderedField)) {
            return $renderedFieldValue ?? false; // those fields are initialized with null in the rule builder
        }

        return $renderedFieldValue;
    }

    /**
     * @param array<string, string> $renderedField
     */
    private function isSwitchOrBoolField(array $renderedField): bool
    {
        return \in_array($renderedField['type'], [CustomFieldTypes::BOOL, CustomFieldTypes::SWITCH], true);
    }
}
