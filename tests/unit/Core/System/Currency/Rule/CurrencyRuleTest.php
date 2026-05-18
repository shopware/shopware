<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Currency\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\Currency\Rule\CurrencyRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(CurrencyRule::class)]
class CurrencyRuleTest extends TestCase
{
    public function testGetName(): void
    {
        $rule = new CurrencyRule();

        static::assertSame('currency', $rule->getName());
    }

    public function testGetConstraints(): void
    {
        $rule = new CurrencyRule();

        static::assertEquals([
            'operator' => RuleConstraints::uuidOperators(false),
            'currencyIds' => RuleConstraints::uuids(),
        ], $rule->getConstraints());
    }

    public function testConstraintsRejectEmptyCurrencyIds(): void
    {
        $violations = $this->validateConstraint('currencyIds', []);

        $this->assertViolationCode($violations, NotBlank::IS_BLANK_ERROR);
    }

    public function testConstraintsRejectStringCurrencyIds(): void
    {
        $violations = $this->validateConstraint('currencyIds', '0915d54fbf80423c917c61ad5a391b48');

        $this->assertViolationCode($violations, Type::INVALID_TYPE_ERROR);
    }

    public function testConstraintsRejectInvalidArrayCurrencyIds(): void
    {
        $violations = $this->validateConstraint('currencyIds', [true, 3, null, '0915d54fbf80423c917c61ad5a391b48']);

        $this->assertViolationCode($violations, ArrayOfUuid::INVALID_TYPE_CODE, 3);
    }

    public function testConstraintsRejectInvalidCurrencyIdsUuid(): void
    {
        $violations = $this->validateConstraint('currencyIds', ['Invalid', '1234abcd']);

        $this->assertViolationCode($violations, ArrayOfUuid::INVALID_TYPE_CODE, 2);
    }

    public function testConstraintsAcceptValidCurrencyIds(): void
    {
        $violations = $this->validateConstraint('currencyIds', [Uuid::randomHex(), Uuid::randomHex()]);

        static::assertCount(0, $violations);
    }

    public function testGetConfig(): void
    {
        $rule = new CurrencyRule();
        $ruleConfig = $rule->getConfig();

        static::assertSame([
            'operatorSet' => [
                'operators' => [
                    Rule::OPERATOR_EQ,
                    Rule::OPERATOR_NEQ,
                ],
                'isMatchAny' => true,
            ],
            'fields' => [
                'currencyIds' => [
                    'name' => 'currencyIds',
                    'type' => 'multi-entity-id-select',
                    'config' => [
                        'entity' => 'currency',
                    ],
                ],
            ],
        ], $ruleConfig->getData());
    }

    /**
     * @param list<string> $currencyIds
     */
    #[DataProvider('matchCurrencyRuleDataProvider')]
    public function testMatch(string $operator, string $currencyId, array $currencyIds): void
    {
        $rule = new CurrencyRule($operator, $currencyIds);

        static::assertTrue($rule->match($this->getCartRuleScope($currencyId)));
    }

    /**
     * @param list<string> $currencyIds
     */
    #[DataProvider('noMatchCurrencyRuleDataProvider')]
    public function testNoMatch(string $operator, string $currencyId, array $currencyIds): void
    {
        $rule = new CurrencyRule($operator, $currencyIds);

        static::assertFalse($rule->match($this->getCartRuleScope($currencyId)));
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: list<string>}>
     */
    public static function matchCurrencyRuleDataProvider(): iterable
    {
        yield 'It matches one currency, equals operator' => [
            Rule::OPERATOR_EQ,
            'id-1',
            [
                'id-1',
            ],
        ];

        yield 'It matches one currency, not equals operator' => [
            Rule::OPERATOR_NEQ,
            'different-currency-id',
            [
                'id-1',
            ],
        ];

        yield 'It matches multiple currencies, equals operator' => [
            Rule::OPERATOR_EQ,
            'id-1',
            [
                'id-2',
                'id-3',
                'id-1',
            ],
        ];

        yield 'It matches multiple currencies, not equals operator' => [
            Rule::OPERATOR_NEQ,
            'different-currency-id',
            [
                'id-1',
                'id-2',
                'id-3',
            ],
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: list<string>}>
     */
    public static function noMatchCurrencyRuleDataProvider(): iterable
    {
        $ids = new IdsCollection();

        yield 'It does not matches one currency, equals operator' => [
            Rule::OPERATOR_EQ,
            'different-currency-id',
            [
                'id-1',
            ],
        ];

        yield 'It does not matches one currency, not equals operator' => [
            Rule::OPERATOR_NEQ,
            'id-1',
            [
                'id-1',
            ],
        ];

        yield 'It does not matches multiple currencies, equals operator' => [
            Rule::OPERATOR_EQ,
            $ids->get('different-currency-id'),
            [
                'id-1',
                'id-2',
                'id-3',
            ],
        ];

        yield 'It does not matches multiple currencies, not equals operator' => [
            Rule::OPERATOR_NEQ,
            'id-1',
            [
                'id-2',
                'id-3',
                'id-1',
            ],
        ];
    }

    private function getCartRuleScope(string $currencyId): CartRuleScope
    {
        $context = Context::createDefaultContext();
        $context->assign(['currencyId' => $currencyId]);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->method('getContext')
            ->willReturn($context);

        $cart = new Cart('bar');

        return new CartRuleScope($cart, $salesChannelContext);
    }

    private function validateConstraint(string $field, mixed $value): ConstraintViolationListInterface
    {
        return Validation::createValidator()->validate($value, (new CurrencyRule())->getConstraints()[$field]);
    }

    private function assertViolationCode(ConstraintViolationListInterface $violations, string $expectedCode, int $expectedCount = 1): void
    {
        static::assertCount($expectedCount, $violations);

        foreach ($violations as $violation) {
            static::assertSame($expectedCode, $violation->getCode());
        }
    }
}
