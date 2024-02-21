<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(LineItemCustomFieldRule::class)]
#[Group('rules')]
class LineItemCustomFieldRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private const CUSTOM_FIELD_NAME = 'custom_test';

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
        $this->salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());
    }

    public function testGetName(): void
    {
        $rule = new LineItemCustomFieldRule();
        static::assertSame('cartLineItemCustomField', $rule->getName());
    }

    public function testGetConstraints(): void
    {
        $rule = new LineItemCustomFieldRule();
        $ruleConstraints = $rule->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Rule Constraint operator is not defined');
        static::assertArrayHasKey('renderedField', $ruleConstraints, 'Rule Constraint renderedField is not defined');
        static::assertArrayHasKey('renderedFieldValue', $ruleConstraints, 'Rule Constraint renderedFieldValue is not defined');
        static::assertArrayHasKey('selectedField', $ruleConstraints, 'Rule Constraint selectedField is not defined');
        static::assertArrayHasKey('selectedFieldSet', $ruleConstraints, 'Rule Constraint selectedFieldSet is not defined');
    }

    public function testBooleanCustomFieldFalseWithNoValue(): void
    {
        $rule = self::setupBoolRule(false);
        $scope = new LineItemScope($this->createLineItemWithCustomFields(), $this->salesChannelContext);
        static::assertTrue($rule->match($scope));
    }

    public function testBooleanCustomFieldFalse(): void
    {
        $rule = self::setupBoolRule(false);
        $scope = new LineItemScope($this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => false]), $this->salesChannelContext);
        static::assertTrue($rule->match($scope));
    }

    public function testBooleanCustomFieldNull(): void
    {
        $rule = self::setupBoolRule(null);
        $scope = new LineItemScope($this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => false]), $this->salesChannelContext);
        static::assertTrue($rule->match($scope));
    }

    public function testBooleanCustomFieldWithNonBooleanData(): void
    {
        $rule = self::setupBoolRule('true');
        $scope = new LineItemScope($this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => true]), $this->salesChannelContext);
        static::assertTrue($rule->match($scope));
    }

    public function testTextCustomFieldUnequalOperator(): void
    {
        // Case: the rule checks for some text but the line item custom field value is null
        // 'testValue' != null -> true
        $rule = new LineItemCustomFieldRule();
        $rule->assign(
            [
                'operator' => Rule::OPERATOR_NEQ,
                'renderedField' => [
                    'type' => 'text',
                    'name' => self::CUSTOM_FIELD_NAME,
                ],
                'renderedFieldValue' => 'testValue',
            ]
        );
        $scope = new LineItemScope($this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => null]), $this->salesChannelContext);
        static::assertTrue($rule->match($scope));
    }

    public function testBooleanCustomFieldInvalid(): void
    {
        $rule = self::setupBoolRule(false);
        $scope = new LineItemScope($this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => true]), $this->salesChannelContext);
        static::assertFalse($rule->match($scope));
    }

    public function testWithoutCustomField(): void
    {
        $rule = self::setupBoolRule(false);
        $scope = new LineItemScope($this->createLineItem(), $this->salesChannelContext);
        static::assertFalse($rule->match($scope));

        $rule->assign(['operator' => Rule::OPERATOR_NEQ]);

        static::assertTrue($rule->match($scope));
    }

    public function testStringCustomField(): void
    {
        $rule = self::setupStringRule('my_test_value');
        $scope = new LineItemScope($this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => 'my_test_value']), $this->salesChannelContext);
        static::assertTrue($rule->match($scope));
    }

    public function testStringCustomFieldInvalid(): void
    {
        $rule = self::setupStringRule('my_test_value');
        $scope = new LineItemScope($this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => 'my_invalid_value']), $this->salesChannelContext);
        static::assertFalse($rule->match($scope));
    }

    public function testMultiSelectCustomField(): void
    {
        $rule = self::setupSelectRule([1, 2], ['componentName' => 'sw-multi-select']);
        $scope = new LineItemScope($this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => [1]]), $this->salesChannelContext);
        static::assertTrue($rule->match($scope));
    }

    public function testMultiSelectCustomFieldInvalid(): void
    {
        $rule = self::setupSelectRule([1, 2], ['componentName' => 'sw-multi-select']);
        $scope = new LineItemScope($this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => [3]]), $this->salesChannelContext);
        static::assertFalse($rule->match($scope));
    }

    public function testFloatCustomField(): void
    {
        $rule = self::setupFloatRule(1);
        $scope = new LineItemScope($this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => 1]), $this->salesChannelContext);
        static::assertTrue($rule->match($scope));
    }

    public function testFloatCustomFieldInvalid(): void
    {
        $rule = self::setupFloatRule('empty');
        $scope = new LineItemScope($this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => 2]), $this->salesChannelContext);
        static::assertFalse($rule->match($scope));
    }

    /**
     * @param bool|string|null $customFieldValueInLineItem
     */
    #[DataProvider('customFieldCartScopeProvider')]
    public function testCustomFieldCartScope(
        LineItemCustomFieldRule $rule,
        $customFieldValueInLineItem,
        bool $result
    ): void {
        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => $customFieldValueInLineItem]),
        ]);

        $cart = $this->createCart($lineItemCollection);
        $scope = new CartRuleScope($cart, $this->salesChannelContext);
        static::assertSame($result, $rule->match($scope));
    }

    /**
     * @param bool|string|null $customFieldValueInLineItem
     */
    #[DataProvider('customFieldCartScopeProvider')]
    public function testCustomFieldCartScopeNested(
        LineItemCustomFieldRule $rule,
        $customFieldValueInLineItem,
        bool $result
    ): void {
        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => $customFieldValueInLineItem]),
        ]);

        $containerLineItem = $this->createContainerLineItem($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $scope = new CartRuleScope($cart, $this->salesChannelContext);
        static::assertSame($result, $rule->match($scope));
    }

    /**
     * @return iterable<string, array<string, array<int, int>|LineItemCustomFieldRule|string|bool>>
     */
    public static function customFieldCartScopeProvider(): iterable
    {
        yield 'testBooleanCustomFieldTrue' => [
            'rule' => self::setupBoolRule(true),
            'customFieldValueInCustomer' => true,
            'result' => true,
        ];
        yield 'testBooleanCustomFieldFalse' => [
            'rule' => self::setupBoolRule(false),
            'customFieldValueInCustomer' => false,
            'result' => true,
        ];
        yield 'testBooleanCustomFieldNull' => [
            'rule' => self::setupBoolRule(null),
            'customFieldValueInCustomer' => false,
            'result' => true,
        ];
        yield 'testBooleanCustomFieldInvalid' => [
            'rule' => self::setupBoolRule(false),
            'customFieldValueInCustomer' => true,
            'result' => false,
        ];
        yield 'testStringCustomField' => [
            'rule' => self::setupStringRule('my_test_value'),
            'customFieldValueInCustomer' => 'my_test_value',
            'result' => true,
        ];
        yield 'testStringCustomFieldInvalid' => [
            'rule' => self::setupStringRule('my_test_value'),
            'customFieldValueInCustomer' => 'my_invalid_value',
            'result' => false,
        ];
        yield 'testMultiSelectCustomField' => [
            'rule' => self::setupSelectRule([1, 2], ['componentName' => 'sw-multi-select']),
            'customFieldValueInCustomer' => [1],
            'result' => true,
        ];
        yield 'testMultiSelectCustomFieldInvalid' => [
            'rule' => self::setupSelectRule([1, 2], ['componentName' => 'sw-multi-select']),
            'customFieldValueInCustomer' => [3],
            'result' => false,
        ];
        yield 'testMultiSelectCustomFieldNull' => [
            'rule' => self::setupSelectRule(null, ['componentName' => 'sw-multi-select']),
            'customFieldValueInCustomer' => [3],
            'result' => false,
        ];
    }

    /**
     * @param array<string, array<int>|bool|int|string|null> $customFields
     */
    private function createLineItemWithCustomFields(array $customFields = []): LineItem
    {
        return $this->createLineItem()->setPayloadValue('customFields', $customFields);
    }

    private static function setupFloatRule(string|float $customFieldValue): LineItemCustomFieldRule
    {
        $rule = new LineItemCustomFieldRule();
        $rule->assign(
            [
                'operator' => Rule::OPERATOR_EQ,
                'renderedField' => [
                    'type' => 'float',
                    'name' => self::CUSTOM_FIELD_NAME,
                ],
                'renderedFieldValue' => $customFieldValue,
            ]
        );

        return $rule;
    }

    /**
     * @param array<int>|bool|string|null $customFieldValue
     */
    private static function setupBoolRule(array|bool|string|null $customFieldValue): LineItemCustomFieldRule
    {
        $rule = new LineItemCustomFieldRule();
        $rule->assign(
            [
                'operator' => Rule::OPERATOR_EQ,
                'renderedField' => [
                    'type' => 'bool',
                    'name' => self::CUSTOM_FIELD_NAME,
                ],
                'renderedFieldValue' => $customFieldValue,
            ]
        );

        return $rule;
    }

    /**
     * @param array<int>|bool|string|null $customFieldValue
     */
    private static function setupStringRule(array|bool|string|null $customFieldValue): LineItemCustomFieldRule
    {
        $rule = new LineItemCustomFieldRule();

        $rule->assign(
            [
                'operator' => Rule::OPERATOR_EQ,
                'renderedField' => [
                    'type' => 'string',
                    'name' => self::CUSTOM_FIELD_NAME,
                ],
                'renderedFieldValue' => $customFieldValue,
            ]
        );

        return $rule;
    }

    /**
     * @param array<int>|bool|string|null $customFieldValue
     * @param array<string, string> $config
     */
    private static function setupSelectRule(array|bool|string|null $customFieldValue, array $config = []): LineItemCustomFieldRule
    {
        $rule = new LineItemCustomFieldRule();
        $rule->assign(
            [
                'operator' => Rule::OPERATOR_EQ,
                'renderedField' => [
                    'type' => 'select',
                    'name' => self::CUSTOM_FIELD_NAME,
                    'config' => $config,
                ],
                'renderedFieldValue' => $customFieldValue,
            ]
        );

        return $rule;
    }
}
