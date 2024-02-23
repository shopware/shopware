<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\CustomerCustomFieldRule;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CustomerCustomFieldRule::class)]
#[Group('rules')]
class CustomerCustomFieldRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private const CUSTOM_FIELD_NAME = 'custom_test';

    private MockObject $customer;

    private CheckoutRuleScope $scope;

    protected function setUp(): void
    {
        $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $this->customer = $this->getMockBuilder(CustomerEntity::class)->disableOriginalConstructor()->getMock();
        $salesChannelContext->method('getCustomer')->willReturn($this->customer);

        $this->scope = new CheckoutRuleScope($salesChannelContext);
    }

    public function testGetName(): void
    {
        $rule = new CustomerCustomFieldRule();
        static::assertSame('customerCustomField', $rule->getName());
    }

    public function testGetConstraints(): void
    {
        $rule = new CustomerCustomFieldRule();
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
        $this->setCustomerCustomFields([]);
        static::assertTrue($rule->match($this->scope));
    }

    public function testMatchWithWrongRuleScope(): void
    {
        $scope = $this->createMock(TestRuleScope::class);

        $rule = new CustomerCustomFieldRule();
        $match = $rule->match($scope);

        static::assertFalse($match);
    }

    public function testMatchWithoutCustomer(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(null);

        $scope = new CheckoutRuleScope($context);

        $rule = self::setupBoolRule(true);

        static::assertFalse($rule->match($scope));
    }

    #[DataProvider('getStringRuleValueWhichShouldBeConsideredAsTrueProvider')]
    public function testBooleanCustomFieldTrueWhenIsRuleIsSetupAsString(string $value): void
    {
        $rule = self::setupBoolRule($value);
        $this->setCustomerCustomFields([
            self::CUSTOM_FIELD_NAME => true,
        ]);
        static::assertTrue($rule->match($this->scope));
    }

    #[DataProvider('getStringRuleValueWhichShouldBeConsideredAsFalseProvider')]
    public function testBooleanCustomFieldFalseWhenIsRuleIsSetupAsString(string $value): void
    {
        $rule = self::setupBoolRule($value);
        $this->setCustomerCustomFields([
            self::CUSTOM_FIELD_NAME => false,
        ]);
        static::assertTrue($rule->match($this->scope));
    }

    #[DataProvider('getStringRuleValueWhichShouldBeConsideredAsTrueProvider')]
    public function testBooleanCustomFieldInvalidAsString(string $value): void
    {
        $rule = self::setupBoolRule($value);
        $this->setCustomerCustomFields([
            self::CUSTOM_FIELD_NAME => false,
        ]);
        static::assertFalse($rule->match($this->scope));
    }

    public function testTextCustomFieldUnequalOperator(): void
    {
        // Case: the rule checks for some text but the line item custom field value is null
        // 'testValue' != null -> true
        $rule = new CustomerCustomFieldRule();
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
        $this->setCustomerCustomFields([self::CUSTOM_FIELD_NAME => null]);
        static::assertTrue($rule->match($this->scope));
    }

    /**
     * @param array<int>|bool|string|null $customFieldValueInCustomer
     */
    #[DataProvider('customFieldCheckoutScopeProvider')]
    public function testCustomFieldCheckoutScope(
        CustomerCustomFieldRule $rule,
        array|bool|string|null $customFieldValueInCustomer,
        bool $result
    ): void {
        $this->setCustomerCustomFields([self::CUSTOM_FIELD_NAME => $customFieldValueInCustomer]);
        static::assertSame($result, $rule->match($this->scope));
    }

    /**
     * @return iterable<string, array<string, array<int, int>|CustomerCustomFieldRule|string|bool>>
     */
    public static function customFieldCheckoutScopeProvider(): iterable
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

    /**
     * @param array<string, mixed> $customFields
     */
    private function setCustomerCustomFields(array $customFields = []): void
    {
        $this->customer->method('getCustomFields')->willReturn($customFields);
    }

    /**
     * @param array<int>|bool|string|null $customFieldValue
     */
    private static function setupBoolRule(array|bool|string|null $customFieldValue): CustomerCustomFieldRule
    {
        $rule = new CustomerCustomFieldRule();
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
    private static function setupStringRule(array|bool|string|null $customFieldValue): CustomerCustomFieldRule
    {
        $rule = new CustomerCustomFieldRule();

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
    private static function setupSelectRule(array|bool|string|null $customFieldValue, array $config = []): CustomerCustomFieldRule
    {
        $rule = new CustomerCustomFieldRule();
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
