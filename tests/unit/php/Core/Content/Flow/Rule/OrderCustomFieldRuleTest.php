<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Content\Flow\Rule\FlowRuleScope;
use Shopware\Core\Content\Flow\Rule\OrderCustomFieldRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Customer\Rule\TestRuleScope;

/**
 * @package business-ops
 *
 * @internal
 *
 * @group rules
 *
 * @covers \Shopware\Core\Content\Flow\Rule\OrderCustomFieldRule
 */
#[Package('business-ops')]
class OrderCustomFieldRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private const CUSTOM_FIELD_NAME = 'custom_test';

    private OrderCustomFieldRule $rule;

    private OrderEntity $order;

    protected function setUp(): void
    {
        $this->rule = new OrderCustomFieldRule();

        $this->order = new OrderEntity();
    }

    public function testGetName(): void
    {
        static::assertSame('orderCustomField', $this->rule->getName());
    }

    public function testMatchWithWrongRuleScope(): void
    {
        $scope = $this->createMock(TestRuleScope::class);

        $match = $this->rule->match($scope);

        static::assertFalse($match);
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

    public function testGetConstraintsWithRenderedField(): void
    {
        $this->rule->assign([
            'renderedField' => [
                'type' => 'string',
            ],
        ]);

        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('renderedFieldValue', $ruleConstraints, 'Rule Constraint renderedFieldValue is not defined');
    }

    /**
     * @dataProvider getCaseTestMatchBoolCustomFieldValues
     *
     * @param array<string, bool> $customFields
     */
    public function testMatchWithBoolCustomFieldValues(
        array $customFields,
        bool $renderedFieldValue,
        bool $isMatching
    ): void {
        $this->order->assign(['customFields' => $customFields]);
        $scope = $this->createScope($this->order);
        $this->assignRule('bool', $renderedFieldValue);

        static::assertEquals($this->rule->match($scope), $isMatching);
    }

    public static function getCaseTestMatchBoolCustomFieldValues(): \Generator
    {
        yield 'not match with custom field null' => [
            [],
            true,
            false,
        ];

        yield 'match with custom field null' => [
            [],
            false,
            true,
        ];

        yield 'not match with custom field false' => [
            [self::CUSTOM_FIELD_NAME => false],
            true,
            false,
        ];

        yield 'match with custom field false' => [
            [self::CUSTOM_FIELD_NAME => false],
            false,
            true,
        ];

        yield 'not match with custom field true' => [
            [self::CUSTOM_FIELD_NAME => true],
            false,
            false,
        ];

        yield 'match with custom field true' => [
            [self::CUSTOM_FIELD_NAME => true],
            true,
            true,
        ];
    }

    /**
     * @dataProvider getStringRuleValueWhichShouldBeConsideredAsTrueProvider
     */
    public function testBooleanCustomFieldTrueWhenIsRuleIsSetupAsString(string $renderedFieldValue): void
    {
        $this->order->assign(['customFields' => [
            self::CUSTOM_FIELD_NAME => true,
        ]]);
        $scope = $this->createScope($this->order);
        $this->assignRule('bool', $renderedFieldValue);

        static::assertTrue($this->rule->match($scope));
    }

    /**
     * @dataProvider getStringRuleValueWhichShouldBeConsideredAsFalseProvider
     */
    public function testBooleanCustomFieldFalseWhenIsRuleIsSetupAsString(string $renderedFieldValue): void
    {
        $this->order->assign(['customFields' => [
            self::CUSTOM_FIELD_NAME => false,
        ]]);
        $scope = $this->createScope($this->order);
        $this->assignRule('bool', $renderedFieldValue);

        static::assertTrue($this->rule->match($scope));
    }

    /**
     * @dataProvider getStringRuleValueWhichShouldBeConsideredAsTrueProvider
     */
    public function testBooleanCustomFieldInvalidAsString(string $renderedFieldValue): void
    {
        $this->order->assign(['customFields' => [
            self::CUSTOM_FIELD_NAME => false,
        ]]);
        $scope = $this->createScope($this->order);
        $this->assignRule('bool', $renderedFieldValue);

        static::assertFalse($this->rule->match($scope));
    }

    public function testBooleanCustomFieldNull(): void
    {
        $this->order->assign(['customFields' => [
            self::CUSTOM_FIELD_NAME => false,
        ]]);
        $scope = $this->createScope($this->order);
        $this->assignRule('bool', null);

        static::assertTrue($this->rule->match($scope));
    }

    public function testTextCustomFieldUnequalOperator(): void
    {
        $this->order->assign(['customFields' => [
            self::CUSTOM_FIELD_NAME => null,
        ]]);
        $scope = $this->createScope($this->order);

        $this->assignRule('text', 'testValue');
        $this->rule->assign(
            [
                'operator' => $this->rule::OPERATOR_NEQ,
            ]
        );

        static::assertTrue($this->rule->match($scope));
    }

    public function testTextCustomFieldNull(): void
    {
        $this->order->assign(['customFields' => []]);
        $scope = $this->createScope($this->order);
        $this->assignRule('text', 'testValue');

        static::assertFalse($this->rule->match($scope));
    }

    public function testBooleanCustomFieldInvalid(): void
    {
        $this->order->assign(['customFields' => [
            self::CUSTOM_FIELD_NAME => true,
        ]]);
        $scope = $this->createScope($this->order);

        $this->assignRule('bool', false);

        static::assertFalse($this->rule->match($scope));
    }

    public function testStringCustomField(): void
    {
        $this->order->assign(['customFields' => [
            self::CUSTOM_FIELD_NAME => 'my_test_value',
        ]]);
        $scope = $this->createScope($this->order);
        $this->assignRule('string', 'my_test_value');

        static::assertTrue($this->rule->match($scope));
    }

    public function testStringCustomFieldInvalid(): void
    {
        $this->order->assign(['customFields' => [
            self::CUSTOM_FIELD_NAME => 'my_invalid_value',
        ]]);
        $scope = $this->createScope($this->order);
        $this->assignRule('string', 'my_test_value');

        static::assertFalse($this->rule->match($scope));
    }

    /**
     * @dataProvider customFieldCheckoutScopeProvider
     */
    public function testCustomFieldCheckoutScope(
        bool|string|null $customFieldValue,
        string $type,
        bool|string|null $customFieldValueInCustomer,
        bool $result
    ): void {
        $this->order->assign(['customFields' => [
            self::CUSTOM_FIELD_NAME => $customFieldValueInCustomer,
        ]]);
        $scope = $this->createScope($this->order);

        $this->assignRule($type, $customFieldValue);

        static::assertSame($result, $this->rule->match($scope));
    }

    /**
     * @return array<string, array<bool|string|null>>
     */
    public static function customFieldCheckoutScopeProvider(): array
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
     * @return array<array<string>>
     */
    public static function getStringRuleValueWhichShouldBeConsideredAsTrueProvider(): array
    {
        return [
            ['yes'],
            ['True'],
            ['1'],
            ['true'],
            ['yes '],
        ];
    }

    /**
     * @return array<array<string>>
     */
    public static function getStringRuleValueWhichShouldBeConsideredAsFalseProvider(): array
    {
        return [
            ['no'],
            ['False'],
            ['0'],
            ['false'],
            ['no '],
            ['some string'],
        ];
    }

    private function createScope(OrderEntity $order): CheckoutRuleScope
    {
        return new FlowRuleScope($order, new Cart('test'), $this->createMock(SalesChannelContext::class));
    }

    private function assignRule(string $type, bool|string|null $renderedFieldValue): void
    {
        $this->rule->assign(
            [
                'operator' => $this->rule::OPERATOR_EQ,
                'renderedField' => [
                    'type' => $type,
                    'name' => self::CUSTOM_FIELD_NAME,
                ],
                'renderedFieldValue' => $renderedFieldValue,
            ]
        );
    }
}
