<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Rule\AffiliateCodeOfOrderRule;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Rule\FlowRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfType;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Customer\Rule\TestRuleScope;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(AffiliateCodeOfOrderRule::class)]
#[Group('rules')]
class AffiliateCodeOfOrderRuleTest extends TestCase
{
    public function testGetConstraints(): void
    {
        $constraints = (new AffiliateCodeOfOrderRule())->getConstraints();

        static::assertArrayHasKey('affiliateCode', $constraints, 'Constraint affiliateCode not found in Rule');
        static::assertEquals($constraints['affiliateCode'], [
            new NotBlank(),
            new ArrayOfType('string'),
        ]);
    }

    public function testName(): void
    {
        $rule = new AffiliateCodeOfOrderRule();
        static::assertSame('orderAffiliateCode', $rule->getName());
    }

    public function testGetConfig(): void
    {
        $config = (new AffiliateCodeOfOrderRule())->getConfig();
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

        $match = (new AffiliateCodeOfOrderRule())->match($scope);

        static::assertFalse($match);
    }

    public function testInvalidCombinationOfValueAndOperator(): void
    {
        $this->expectException(CartException::class);
        $rule = new AffiliateCodeOfOrderRule(Rule::OPERATOR_EQ, null);

        $order = new OrderEntity();
        $order->setAffiliateCode('TestAffiliateCode123');
        $cart = new Cart('ABC');

        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $scope = new FlowRuleScope($order, $cart, $salesChannelContext);
        $rule->match($scope);
    }

    /**
     * @param ?array<string> $ruleCode
     */
    #[DataProvider('getCaseTestMatchValues')]
    public function testMatch(string $operator, ?array $ruleCode, ?string $orderAffiliateCode, bool $isMatching): void
    {
        $rule = new AffiliateCodeOfOrderRule($operator, $ruleCode);

        $order = new OrderEntity();
        $order->setAffiliateCode($orderAffiliateCode);
        $cart = new Cart('ABC');

        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $scope = new FlowRuleScope($order, $cart, $salesChannelContext);
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
            'orderAffiliateCode' => 'testingCode',
            'isMatching' => true,
        ];

        yield 'Equals Operator is matching with multiple values' => [
            'operator' => Rule::OPERATOR_EQ,
            'ruleCode' => ['foobar', 'testingCode'],
            'orderAffiliateCode' => 'testingCode',
            'isMatching' => true,
        ];

        yield 'Equals Operator is not matching' => [
            'operator' => Rule::OPERATOR_EQ,
            'ruleCode' => ['testingCode'],
            'orderAffiliateCode' => 'otherCode',
            'isMatching' => false,
        ];

        yield 'Equals Operator is not matching with multiple values' => [
            'operator' => Rule::OPERATOR_EQ,
            'ruleCode' => ['foobar', 'testingCode'],
            'orderAffiliateCode' => 'otherCode',
            'isMatching' => false,
        ];

        yield 'Not Equals Operator is matching' => [
            'operator' => Rule::OPERATOR_NEQ,
            'ruleCode' => ['testingCode'],
            'orderAffiliateCode' => 'otherCode',
            'isMatching' => true,
        ];

        yield 'Not Equals Operator is not matching' => [
            'operator' => Rule::OPERATOR_NEQ,
            'ruleCode' => ['testingCode'],
            'orderAffiliateCode' => 'testingCode',
            'isMatching' => false,
        ];

        yield 'Not Equals Operator is matching with multiple values' => [
            'operator' => Rule::OPERATOR_NEQ,
            'ruleCode' => ['foobar', 'testingCode'],
            'orderAffiliateCode' => 'otherCode',
            'isMatching' => true,
        ];

        yield 'Empty Operator is matching, because both codes does not exist' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'ruleCode' => null,
            'orderAffiliateCode' => null,
            'isMatching' => true,
        ];

        yield 'Empty Operator is matching, because cart code does not exist' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'ruleCode' => ['testingCode'],
            'orderAffiliateCode' => null,
            'isMatching' => true,
        ];

        yield 'Empty Operator is not matching, because both codes are filled' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'ruleCode' => ['testingCode'],
            'orderAffiliateCode' => 'testingCode',
            'isMatching' => false,
        ];

        yield 'Empty Operator is not matching, because cart code is filled' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'ruleCode' => null,
            'orderAffiliateCode' => 'testingCode',
            'isMatching' => false,
        ];
    }
}
