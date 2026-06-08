<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\PaymentMethodRule;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

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

    public function testConstraintsRejectEmptyPaymentMethodIds(): void
    {
        $violations = $this->validateConstraint('paymentMethodIds', []);

        $this->assertViolationCode($violations, NotBlank::IS_BLANK_ERROR);
    }

    public function testConstraintsRejectStringPaymentMethodIds(): void
    {
        $violations = $this->validateConstraint('paymentMethodIds', '0915d54fbf80423c917c61ad5a391b48');

        $this->assertViolationCode($violations, Type::INVALID_TYPE_ERROR);
    }

    public function testConstraintsRejectInvalidPaymentMethodIdsUuid(): void
    {
        $violations = $this->validateConstraint('paymentMethodIds', [true, 3, null, '0915d54fbf80423c917c61ad5a391b48']);

        $this->assertViolationCode($violations, ArrayOfUuid::INVALID_TYPE_CODE, 3);
    }

    #[DataProvider('validUuidOperators')]
    public function testConstraintsAcceptAvailableOperators(string $operator): void
    {
        $violations = $this->validateConstraint('operator', $operator);

        static::assertCount(0, $violations);
    }

    #[DataProvider('invalidUuidOperators')]
    public function testConstraintsRejectInvalidOperators(mixed $operator): void
    {
        $violations = $this->validateConstraint('operator', $operator);

        $this->assertViolationCode($violations, Choice::NO_SUCH_CHOICE_ERROR);
    }

    public function testConstraintsAcceptValidPaymentMethodIds(): void
    {
        $violations = $this->validateConstraint('paymentMethodIds', [Uuid::randomHex()]);

        static::assertCount(0, $violations);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function validUuidOperators(): \Generator
    {
        yield 'equals' => [Rule::OPERATOR_EQ];
        yield 'not equals' => [Rule::OPERATOR_NEQ];
    }

    /**
     * @return \Generator<string, array{mixed}>
     */
    public static function invalidUuidOperators(): \Generator
    {
        yield 'less than or equals' => [Rule::OPERATOR_LTE];
        yield 'greater than or equals' => [Rule::OPERATOR_GTE];
        yield 'unknown operator' => ['Invalid'];
        yield 'boolean operator' => [true];
        yield 'float operator' => [1.1];
    }

    public function testRuleDoesNotMatchNoPaymentIds(): void
    {
        $rule = new PaymentMethodRule();
        $paymentMethodeEntity = new PaymentMethodEntity();
        $paymentMethodeEntity->setId('foo');

        $salesChannelContextMock = static::createStub(SalesChannelContext::class);
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

        $salesChannelContextMock = static::createStub(SalesChannelContext::class);
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

    private function validateConstraint(string $field, mixed $value): ConstraintViolationListInterface
    {
        return Validation::createValidator()->validate($value, (new PaymentMethodRule())->getConstraints()[$field]);
    }

    private function assertViolationCode(ConstraintViolationListInterface $violations, string $expectedCode, int $expectedCount = 1): void
    {
        static::assertCount($expectedCount, $violations);

        foreach ($violations as $violation) {
            static::assertSame($expectedCode, $violation->getCode());
        }
    }
}
