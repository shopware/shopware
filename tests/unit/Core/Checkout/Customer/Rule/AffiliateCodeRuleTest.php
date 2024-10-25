<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\AffiliateCodeRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfType;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(AffiliateCodeRule::class)]
#[Group('rules')]
class AffiliateCodeRuleTest extends TestCase
{
    public function testGetConstraints(): void
    {
        $constraints = (new AffiliateCodeRule())->getConstraints();

        static::assertArrayHasKey('affiliateCode', $constraints, 'Constraint affiliateCode not found in Rule');
        static::assertEquals($constraints['affiliateCode'], [
            new NotBlank(),
            new ArrayOfType('string'),
        ]);
    }

    public function testName(): void
    {
        $rule = new AffiliateCodeRule();
        static::assertSame('customerAffiliateCode', $rule->getName());
    }

    public function testGetConfig(): void
    {
        $config = (new AffiliateCodeRule())->getConfig();
        static::assertEquals([
            'fields' => [
                'affiliateCode' => [
                    'name' => 'affiliateCode',
                    'type' => 'tagged',
                    'config' => [],
                ],
            ],
            'operatorSet' => [
                'operators' => [Rule::OPERATOR_EQ, Rule::OPERATOR_NEQ, Rule::OPERATOR_EMPTY],
                'isMatchAny' => true,
            ],
        ], $config->getData());
    }

    public function testMatchWithWrongRuleScope(): void
    {
        $scope = $this->createMock(TestRuleScope::class);

        $match = (new AffiliateCodeRule())->match($scope);

        static::assertFalse($match);
    }

    public function testInvalidCombinationOfValueAndOperator(): void
    {
        $this->expectException(UnsupportedValueException::class);
        $rule = new AffiliateCodeRule(Rule::OPERATOR_EQ, null);
        $customer = new CustomerEntity();
        $customer->setAffiliateCode('testing');
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->expects(static::once())
            ->method('getCustomer')
            ->willReturn($customer);

        $scope = new CheckoutRuleScope($salesChannelContext);
        $rule->match($scope);
    }

    /**
     * @param ?array<string> $ruleCode
     */
    #[DataProvider('getCaseTestMatchValues')]
    public function testMatch(string $operator, ?array $ruleCode, ?string $customerCode, bool $hasCustomer, bool $isMatching): void
    {
        $rule = new AffiliateCodeRule($operator, $ruleCode);
        $customer = new CustomerEntity();
        $customer->setAffiliateCode($customerCode);
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->expects(static::once())
            ->method('getCustomer')
            ->willReturn($hasCustomer ? $customer : null);

        $scope = new CheckoutRuleScope($salesChannelContext);
        $match = $rule->match($scope);
        static::assertEquals($match, $isMatching);
    }

    /**
     * @return \Traversable<array<mixed>>
     */
    public static function getCaseTestMatchValues(): \Traversable
    {
        yield 'Equals Operator is matching' => [
            'operator' => Rule::OPERATOR_EQ,
            'ruleCode' => ['testingCode'],
            'customerCode' => 'testingCode',
            'hasCustomer' => true,
            'isMatching' => true,
        ];

        yield 'Equals Operator is matching with multiple values' => [
            'operator' => Rule::OPERATOR_EQ,
            'ruleCode' => ['foobar', 'testingCode'],
            'customerCode' => 'testingCode',
            'hasCustomer' => true,
            'isMatching' => true,
        ];

        yield 'Equals Operator is not matching' => [
            'operator' => Rule::OPERATOR_EQ,
            'ruleCode' => ['testingCode'],
            'customerCode' => 'otherCode',
            'hasCustomer' => true,
            'isMatching' => false,
        ];

        yield 'Equals Operator is not matching with multiple values' => [
            'operator' => Rule::OPERATOR_EQ,
            'ruleCode' => ['foobar', 'testingCode'],
            'customerCode' => 'otherCode',
            'hasCustomer' => true,
            'isMatching' => false,
        ];

        yield 'Not Equals Operator is matching' => [
            'operator' => Rule::OPERATOR_NEQ,
            'ruleCode' => ['testingCode'],
            'customerCode' => 'otherCode',
            'hasCustomer' => true,
            'isMatching' => true,
        ];

        yield 'Not Equals Operator is matching with multiple values' => [
            'operator' => Rule::OPERATOR_NEQ,
            'ruleCode' => ['foobar', 'testingCode'],
            'customerCode' => 'otherCode',
            'hasCustomer' => true,
            'isMatching' => true,
        ];

        yield 'Not Equals Operator is not matching' => [
            'operator' => Rule::OPERATOR_NEQ,
            'ruleCode' => ['testingCode'],
            'customerCode' => 'testingCode',
            'hasCustomer' => true,
            'isMatching' => false,
        ];

        yield 'Not Equals Operator is not matching with multiple values' => [
            'operator' => Rule::OPERATOR_NEQ,
            'ruleCode' => ['foobar', 'testingCode'],
            'customerCode' => 'testingCode',
            'hasCustomer' => true,
            'isMatching' => false,
        ];

        yield 'Empty Operator is matching, because both codes not exists' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'ruleCode' => null,
            'customerCode' => null,
            'hasCustomer' => true,
            'isMatching' => true,
        ];

        yield 'Empty Operator is matching, because customer code not exists' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'ruleCode' => ['testingCode'],
            'customerCode' => null,
            'hasCustomer' => true,
            'isMatching' => true,
        ];

        yield 'Empty Operator is matching, because customer code not exists with multiple values' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'ruleCode' => ['foobar', 'testingCode'],
            'customerCode' => null,
            'hasCustomer' => true,
            'isMatching' => true,
        ];

        yield 'Empty Operator is matching, because customer not exists' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'ruleCode' => ['testingCode'],
            'customerCode' => null,
            'hasCustomer' => false,
            'isMatching' => true,
        ];

        yield 'Empty Operator is not matching, because both codes are filled' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'ruleCode' => ['testingCode'],
            'customerCode' => 'testingCode',
            'hasCustomer' => true,
            'isMatching' => false,
        ];

        yield 'Empty Operator is not matching, because customer code is filled' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'ruleCode' => null,
            'customerCode' => 'testingCode',
            'hasCustomer' => true,
            'isMatching' => false,
        ];
    }
}
