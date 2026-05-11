<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\PaymentMethodRule;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(PaymentMethodRule::class)]
#[Group('rules')]
class PaymentMethodRuleTest extends TestCase
{
    public function testNameReturnsKnownName(): void
    {
        $rule = new PaymentMethodRule();

        static::assertSame('paymentMethod', $rule->getName());
    }

    public function testGetApiAlias(): void
    {
        $rule = new PaymentMethodRule();

        static::assertSame('rule_paymentMethod', $rule->getApiAlias());
    }

    public function testJsonSerializeAddsName(): void
    {
        $rule = new PaymentMethodRule();

        $json = $rule->jsonSerialize();

        static::assertSame('paymentMethod', $json['_name']);
    }

    public function testGetConstraintsOfRule(): void
    {
        $rule = new PaymentMethodRule();

        $constraints = $rule->getConstraints();

        static::assertEquals([
            'paymentMethodIds' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ], $constraints);
    }

    public function testRuleDoesNotMatchNoPaymentIds(): void
    {
        $rule = new PaymentMethodRule();
        $paymentMethodeEntity = new PaymentMethodEntity();
        $paymentMethodeEntity->setId('foo');

        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
        $salesChannelContextMock->method('getPaymentMethod')->willReturn($paymentMethodeEntity);

        $ruleScope = $this->createMock(RuleScope::class);
        $ruleScope->method('getSalesChannelContext')->willReturn($salesChannelContextMock);

        static::assertFalse($rule->match($ruleScope));
    }

    public function testRuleMatchesPaymentId(): void
    {
        $rule = new PaymentMethodRule(Rule::OPERATOR_EQ, ['foo']);
        $paymentMethodeEntity = new PaymentMethodEntity();
        $paymentMethodeEntity->setId('foo');

        $salesChannelContextMock = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
        $salesChannelContextMock->method('getPaymentMethod')->willReturn($paymentMethodeEntity);

        $ruleScope = $this->createMock(RuleScope::class);
        $ruleScope->method('getSalesChannelContext')->willReturn($salesChannelContextMock);

        static::assertTrue($rule->match($ruleScope));
    }

    public function testGetDefaultConfig(): void
    {
        $rule = new PaymentMethodRule();

        $config = $rule->getConfig()->getData();
        static::assertSame([
            'operatorSet' => [
                'operators' => [
                    Rule::OPERATOR_EQ,
                    Rule::OPERATOR_NEQ,
                ],
                'isMatchAny' => true,
            ],
            'fields' => [
                'paymentMethodIds' => [
                    'name' => 'paymentMethodIds',
                    'type' => 'multi-entity-id-select',
                    'config' => [
                        'entity' => 'payment_method',
                    ],
                ],
            ],
        ], $config);
    }
}
