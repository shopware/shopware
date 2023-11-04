<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * @group rules
 */
#[Package('business-ops')]
class LineItemCustomFieldRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private const CUSTOM_FIELD_NAME = 'custom_test';

    private LineItemCustomFieldRule $rule;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->rule = new LineItemCustomFieldRule();

        $this->salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
        $this->salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemCustomField', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Rule Constraint operator is not defined');
        static::assertArrayHasKey('renderedField', $ruleConstraints, 'Rule Constraint renderedField is not defined');
        static::assertArrayHasKey('renderedFieldValue', $ruleConstraints, 'Rule Constraint renderedFieldValue is not defined');
        static::assertArrayHasKey('selectedField', $ruleConstraints, 'Rule Constraint selectedField is not defined');
        static::assertArrayHasKey('selectedFieldSet', $ruleConstraints, 'Rule Constraint selectedFieldSet is not defined');
    }

    public function testBooleanCustomFieldFalseWithNoValue(): void
    {
        $this->setupRule(false, 'bool');
        $scope = new LineItemScope($this->createLineItemWithCustomFields(), $this->salesChannelContext);
        static::assertTrue($this->rule->match($scope));
    }

    public function testBooleanCustomFieldFalse(): void
    {
        $this->setupRule(false, 'bool');
        $scope = new LineItemScope($this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => false]), $this->salesChannelContext);
        static::assertTrue($this->rule->match($scope));
    }

    public function testBooleanCustomFieldNull(): void
    {
        $this->setupRule(null, 'bool');
        $scope = new LineItemScope($this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => false]), $this->salesChannelContext);
        static::assertTrue($this->rule->match($scope));
    }

    public function testBooleanCustomFieldWithNonBooleanData(): void
    {
        $this->setupRule('this should be true', 'bool');
        $scope = new LineItemScope($this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => true]), $this->salesChannelContext);
        static::assertTrue($this->rule->match($scope));
    }

    public function testTextCustomFieldUnequalOperator(): void
    {
        // Case: the rule checks for some text but the line item custom field value is null
        // 'testValue' != null -> true
        $this->setupRule('testValue', 'text');
        $this->rule->assign(
            [
                'operator' => Rule::OPERATOR_NEQ,
            ]
        );
        $scope = new LineItemScope($this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => null]), $this->salesChannelContext);
        static::assertTrue($this->rule->match($scope));
    }

    public function testBooleanCustomFieldInvalid(): void
    {
        $this->setupRule(false, 'bool');
        $scope = new LineItemScope($this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => true]), $this->salesChannelContext);
        static::assertFalse($this->rule->match($scope));
    }

    public function testWithoutCustomField(): void
    {
        $this->setupRule(false, 'bool');
        $scope = new LineItemScope($this->createLineItem(), $this->salesChannelContext);
        static::assertFalse($this->rule->match($scope));

        $this->rule->assign(['operator' => Rule::OPERATOR_NEQ]);

        static::assertTrue($this->rule->match($scope));
    }

    public function testStringCustomField(): void
    {
        $this->setupRule('my_test_value', 'string');
        $scope = new LineItemScope($this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => 'my_test_value']), $this->salesChannelContext);
        static::assertTrue($this->rule->match($scope));
    }

    public function testStringCustomFieldInvalid(): void
    {
        $this->setupRule('my_test_value', 'string');
        $scope = new LineItemScope($this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => 'my_invalid_value']), $this->salesChannelContext);
        static::assertFalse($this->rule->match($scope));
    }

    /**
     * @dataProvider customFieldCartScopeProvider
     *
     * @param bool|string|null $customFieldValue
     * @param bool|string|null $customFieldValueInLineItem
     */
    public function testCustomFieldCartScope(
        $customFieldValue,
        string $type,
        $customFieldValueInLineItem,
        bool $result
    ): void {
        $this->setupRule($customFieldValue, $type);
        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => $customFieldValueInLineItem]),
        ]);

        $cart = $this->createCart($lineItemCollection);
        $scope = new CartRuleScope($cart, $this->salesChannelContext);
        static::assertSame($result, $this->rule->match($scope));
    }

    /**
     * @dataProvider customFieldCartScopeProvider
     *
     * @param bool|string|null $customFieldValue
     * @param bool|string|null $customFieldValueInLineItem
     */
    public function testCustomFieldCartScopeNested(
        $customFieldValue,
        string $type,
        $customFieldValueInLineItem,
        bool $result
    ): void {
        $this->setupRule($customFieldValue, $type);
        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithCustomFields([self::CUSTOM_FIELD_NAME => $customFieldValueInLineItem]),
        ]);

        $containerLineItem = $this->createContainerLineItem($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $scope = new CartRuleScope($cart, $this->salesChannelContext);
        static::assertSame($result, $this->rule->match($scope));
    }

    /**
     * @return array<string, array<bool|string|null>>
     */
    public static function customFieldCartScopeProvider(): array
    {
        return [
            'testBooleanCustomFieldFalse' => [false, 'bool', false, true],
            'testBooleanCustomFieldNull' => [null, 'bool', false, true],
            'testBooleanCustomFieldInvalid' => [false, 'bool', true, false],
            'testStringCustomField' => ['my_test_value', 'string', 'my_test_value', true],
            'testStringCustomFieldInvalid' => ['my_test_value', 'string', 'my_invalid_value', false],
        ];
    }

    /**
     * @param array<string, bool|string|null> $customFields
     */
    private function createLineItemWithCustomFields(array $customFields = []): LineItem
    {
        return $this->createLineItem()->setPayloadValue('customFields', $customFields);
    }

    /**
     * @param bool|string|null $customFieldValue
     */
    private function setupRule(mixed $customFieldValue, string $type): void
    {
        $this->rule->assign(
            [
                'operator' => Rule::OPERATOR_EQ,
                'renderedField' => [
                    'type' => $type,
                    'name' => self::CUSTOM_FIELD_NAME,
                ],
                'renderedFieldValue' => $customFieldValue,
            ]
        );
    }
}
