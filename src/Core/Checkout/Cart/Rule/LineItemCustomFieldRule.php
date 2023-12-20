<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\CustomFieldRule;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Util\ArrayComparator;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\Validator\Constraint;

#[Package('services-settings')]
class LineItemCustomFieldRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemCustomField';

    /**
     * @var array<string|int|bool|float>|string|int|float|bool|null
     */
    protected $renderedFieldValue;

    protected ?string $selectedField = null;

    protected ?string $selectedFieldSet = null;

    /**
     * @param array<string, mixed> $renderedField
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
        if ($scope instanceof LineItemScope) {
            return $this->isCustomFieldValid($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $lineItem) {
            if ($this->isCustomFieldValid($lineItem)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array|Constraint[][]
     */
    public function getConstraints(): array
    {
        return CustomFieldRule::getConstraints($this->renderedField);
    }

    /**
     * @throws UnsupportedOperatorException
     */
    private function isCustomFieldValid(LineItem $lineItem): bool
    {
        $customFields = $lineItem->getPayloadValue('customFields');
        if ($customFields === null) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        $actual = CustomFieldRule::getValue($customFields, $this->renderedField);
        $expected = CustomFieldRule::getExpectedValue($this->renderedFieldValue, $this->renderedField);

        if ($actual === null) {
            if ($this->operator === self::OPERATOR_NEQ) {
                return $actual !== $expected;
            }

            return false;
        }

        if (CustomFieldRule::isFloat($this->renderedField)) {
            return FloatComparator::compare((float) $actual, (float) $expected, $this->operator);
        }

        if (CustomFieldRule::isArray($this->renderedField)) {
            return ArrayComparator::compare((array) $actual, (array) $expected, $this->operator);
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
}
