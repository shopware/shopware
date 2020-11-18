<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class LineItemCustomFieldRule extends Rule
{
    /** @var string */
    protected $operator;

    /** @var array */
    protected $renderedField;

    /** @var mixed */
    protected $renderedFieldValue;

    public function getName(): string
    {
        return 'cartLineItemCustomField';
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->isCustomFieldValid($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems() as $lineItem) {
            if ($this->isCustomFieldValid($lineItem)) {
                return true;
            }
        }

        return false;
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
    private function isCustomFieldValid(LineItem $lineItem): bool
    {
        try {
            $customFields = $lineItem->getPayloadValue('customFields');

            $actual = $this->getValue($customFields, $this->renderedField);

            if ($actual === null) {
                return false;
            }

            $expected = $this->getExpectedValue($this->renderedFieldValue, $this->renderedField);
        } catch (PayloadKeyNotFoundException $e) {
            return false;
        }

        switch ($this->operator) {
            case self::OPERATOR_NEQ:
                return $actual !== $expected;
            case self::OPERATOR_GTE:
                return $actual >= $expected;
            case self::OPERATOR_LTE:
                return $actual <= $expected;
            case self::OPERATOR_EQ:
                return $actual === $expected;
            case self::OPERATOR_GT:
                return $actual > $expected;
            case self::OPERATOR_LT:
                return $actual < $expected;
            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
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
     * @return string|int|float|bool|null
     */
    private function getValue(array $customFields, array $renderedField)
    {
        if (in_array($renderedField['type'], [CustomFieldTypes::BOOL, CustomFieldTypes::SWITCH], true)) {
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
     *
     * @return string|int|float|bool|null
     */
    private function getExpectedValue($renderedFieldValue, array $renderedField)
    {
        if (in_array($renderedField['type'], [CustomFieldTypes::BOOL, CustomFieldTypes::SWITCH], true)) {
            return $renderedFieldValue ?? false; // those fields are initialized with null in the rule builder
        }

        return $renderedFieldValue;
    }
}
